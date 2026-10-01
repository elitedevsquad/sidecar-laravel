export class Sidecar {
    constructor() {
        this.baseUrl = this._resolveBaseUrl();
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
        this.consoleShown = false;
        this.tester = null;
        this.init();
    }

    _resolveBaseUrl() {
        if (window.__sidecarBaseUrl) {
            return window.__sidecarBaseUrl.replace(/\/$/, "");
        }

        const scriptTag = document.querySelector('script[src*="__devsquad-sidecar"]');
        if (scriptTag) {
            try {
                const url = new URL(scriptTag.src);
                return url.origin;
            } catch (_) {}
        }

        return window.location.origin;
    }

    async init() {
        await this.fetchInitialData(true);
        this.setupEventListeners();
        this.injectBadge();
    }

    injectBadge() {
        if (!this.data?.badge_fallback) return;

        if (document.documentElement.dataset.sidecar) return;

        if (document.getElementById('devsquad-env-badge')) return;

        if (document.getElementById('sidecar-badge')) return;

        const stored = sessionStorage.getItem('sidecar_badge_dismissed');
        if (stored === '1') return;

        const env = this.data.environment || 'local';
        const branch = this.data.branch || 'main';
        const format = this.data.badge_fallback;
        const appTag = this.data.app_tag;

        let text = env;
        if (format === 'branch') {
            text = branch;
        } else if (format === 'env_branch') {
            text = `${env} \u00B7 ${branch}`;
        } else if (format === 'show_tag' && appTag) {
            text = `Tag: ${appTag}`;
        }

        const colors = {
            staging: '#d70745',
            sandbox: '#0849ec',
            local: '#31b705'
        };

        const bgColor = colors[env] || colors.local;

        const badge = document.createElement('div');
        badge.id = 'sidecar-badge';
        badge.innerText = text;

        Object.assign(badge.style, {
            position: 'fixed',
            zIndex: '999999',
            top: '12px',
            left: '12px',
            padding: '5px 12px',
            background: bgColor,
            color: '#fff',
            fontFamily: 'system-ui, -apple-system, sans-serif',
            fontSize: '12px',
            fontWeight: '600',
            borderRadius: '16px',
            cursor: 'pointer',
            boxShadow: '0 2px 8px rgba(0,0,0,0.2)',
            opacity: '1',
            transition: 'opacity 0.2s',
            lineHeight: '1'
        });

        badge.addEventListener('click', () => {
            badge.style.opacity = '0';
            badge.style.pointerEvents = 'none';
            sessionStorage.setItem('sidecar_badge_dismissed', '1');
        });

        document.body.appendChild(badge);
    }

    async request(endpoint, options = {}) {
        const { headers: extraHeaders, ...rest } = options;

        try {
            const response = await fetch(this.baseUrl + endpoint, {
                ...rest,
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": this.csrfToken,
                    ...(extraHeaders || {}),
                },
            });

            if (!response.ok) {
                if (response.status !== 423) {
                    localStorage.setItem("sidecar_authenticated", "false");
                }

                let errorDetail = {
                    statusCode: response.status,
                    message: response.statusText
                };

                try {
                    const errorJson = await response.json();
                    errorDetail = { ...errorDetail, ...errorJson };
                } catch (_) {
                }

                return { error: errorDetail };
            }

            return await response.json();
        } catch (error) {
            return { error: error.message };
        }
    }

    async fetchInitialData(withoutUsers = false) {
        const hasExtension = !!document.documentElement.dataset.sidecar;
        const params = new URLSearchParams({ without_users: withoutUsers || hasExtension ? "true" : "false" });

        if (!hasExtension) {
            params.set("legacy_commands", "true");
        }

        const data = await this.request("/__devsquad-sidecar/data?" + params.toString(), {});

        if (data.error) {
            if (data.error.statusCode === 403) {
                this.dispatch("sidecar:auth:failed", data.error);
            }
            return;
        }

        if (!this.consoleShown) {
            console.log(
                `\n%cDevSquad Sidecar Enabled\n%cProject: ${data.project_name}`,
                "color:#28ef00;font-size:1em;",
                "color:#aaa;font-size:0.9em;"
            );
            this.consoleShown = true;
        }

        this.data = data;
        this.dispatch("sidecar:to:extension:data", data);
    }

    setupEventListeners() {

        window.addEventListener("sidecar:to:page:selectUser", ({ detail }) => {
            this.handleUserLogin(detail.id);
        });

        window.addEventListener("sidecar:to:page::refresh", async () => {
            await this.fetchInitialData();
        });

        window.addEventListener("sidecar:to:page:listCommands", async () => {
            await this.handleListCommands();
        });

        window.addEventListener("sidecar:to:page:fetchLogs", async () => {
            await this.handleFetchLogs();
        });

        window.addEventListener("sidecar:to:page:listUsers", async ({ detail }) => {
            await this.handleListUsers(detail || {});
        });

        window.addEventListener("sidecar:to:page:setTester", ({ detail }) => {
            this.tester = detail?.id ? { id: String(detail.id), name: String(detail.name || "") } : null;
        });

        window.addEventListener("sidecar:to:page:presence", async ({ detail }) => {
            await this.handlePresence(detail || {});
        });

        window.addEventListener("sidecar:to:page:activity", async ({ detail }) => {
            await this.handleActivity(detail || {});
        });

        const commandEndpoints = {
            "sidecar:to:page:executeCommand": ["/__devsquad-sidecar/execute-command", "sidecar:to:extension:commandOutput"],
            "sidecar:to:page:executeTinker": ["/__devsquad-sidecar/execute-tinker", "sidecar:to:extension:tinkerOutput"],
            "sidecar:to:page:executeFakeClock": ["/__devsquad-sidecar/execute-fake-clock", "sidecar:to:extension:fakeClockOutput"],
            "sidecar:to:page:executeTinkerOnQueue": ["/__devsquad-sidecar/execute-tinker-on-queue", "sidecar:to:extension:tinkerOutput"],
            "sidecar:to:page:clearUserCache": ["/__devsquad-sidecar/clear-user-cache", "sidecar:to:extension:clearUserCacheOutput"],
        };

        for (const event in commandEndpoints) {
            const [endpoint, outputEvent] = commandEndpoints[event];
            window.addEventListener(event, ({ detail }) => this.handleCommand(endpoint, detail, outputEvent));
        }
    }

    presenceKind(endpoint) {
        if (endpoint.endsWith("/execute-tinker-on-queue")) return "tinker-queue";
        if (endpoint.endsWith("/execute-tinker")) return "tinker";
        if (endpoint.endsWith("/execute-fake-clock")) return "clock";

        return "command";
    }

    testerHeaders(endpoint) {
        if (!this.tester?.id) return {};

        if (!/\/execute-|\/login-as$/.test(endpoint)) return {};

        return { "X-Sidecar-Tester": `${this.tester.id};${this.tester.name || ""}` };
    }

    async handlePresence(detail) {
        let data;

        if (detail.action === "stop") {
            data = await this.request("/__devsquad-sidecar/presence", {
                method: "DELETE",
                body: JSON.stringify({ id: detail.id }),
            });
        } else if (detail.action === "read") {
            data = await this.request("/__devsquad-sidecar/activity");
        } else {
            data = await this.request("/__devsquad-sidecar/presence", {
                method: "POST",
                body: JSON.stringify({ id: detail.id, name: detail.name, ttl: detail.ttl }),
            });
        }

        this.dispatch("sidecar:to:extension:presence", { ...data, requestId: detail.requestId ?? null });
    }

    async handleActivity(detail) {
        const params = new URLSearchParams();

        if (detail.full) params.set("full", "1");
        if (detail.tester) params.set("tester", detail.tester);
        if (detail.day) params.set("day", detail.day);

        const path = detail.id
            ? `/__devsquad-sidecar/activity/${encodeURIComponent(detail.id)}`
            : `/__devsquad-sidecar/activity?${params.toString()}`;

        const data = await this.request(path);

        this.dispatch("sidecar:to:extension:activity", {
            ...(data.error ? { error: data.error } : data),
            requestId: detail.requestId ?? null,
        });
    }

    async handleUserLogin(userId) {
        const data = await this.request("/__devsquad-sidecar/login-as", {
            method: "POST",
            headers: this.testerHeaders("/__devsquad-sidecar/login-as"),
            body: JSON.stringify({ user_id: userId }),
        });

        if (data.redirect) {
            window.location.href = data.redirect;
        }
    }

    async handleListCommands() {
        const data = await this.request("/__devsquad-sidecar/commands");

        this.dispatch("sidecar:to:extension:commands", {
            commands: data.commands ?? [],
            generatedAt: data.generated_at ?? null,
            error: data.commands ? null : (data.error?.message ?? "Could not read the command list."),
        });
    }

    async handleListUsers({ page, per_page, search, role, ids, requestId }) {
        const params = new URLSearchParams();

        if (page) params.set("page", String(page));
        if (per_page) params.set("per_page", String(per_page));
        if (search) params.set("search", search);
        if (role) params.set("role", role);
        if (Array.isArray(ids) && ids.length) params.set("ids", ids.join(","));

        const data = await this.request("/__devsquad-sidecar/users?" + params.toString());

        this.dispatch("sidecar:to:extension:users", data.error
            ? { error: data.error.message ?? String(data.error), requestId: requestId ?? null }
            : { ...data, requestId: requestId ?? null });
    }

    async handleFetchLogs() {
        const data = await this.request("/__devsquad-sidecar/logs");

        this.dispatch("sidecar:to:extension:logs", data.error
            ? { error: data.error.message ?? String(data.error) }
            : data);
    }

    async handleCommand(endpoint, payload, outputEvent) {
        const data = await this.request(endpoint, {
            method: "POST",
            headers: this.testerHeaders(endpoint),
            body: JSON.stringify(payload),
        });

        if (data.error?.statusCode === 423) {
            this.dispatch("sidecar:to:extension:presenceRequired", {
                testers: data.error.testers || [],
                message: data.error.message || "Another tester is active.",
                outputEvent,
                payload,
                runId: payload?.runId ?? null,
                kind: this.presenceKind(endpoint),
            });
            return;
        }

        if (data.error) {
            console.warn('Sidecar: ', data);
        }

        const output = data.output ?? data.error?.message ?? "";

        this.dispatch(outputEvent, payload?.runId ? { output, runId: payload.runId } : output);
    }

    dispatch(event, detail) {
        window.dispatchEvent(new CustomEvent(event, { detail }));
    }
}

document.addEventListener("DOMContentLoaded", () => new Sidecar());
