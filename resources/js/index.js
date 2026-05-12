export class Sidecar {
    constructor() {
        this.baseUrl = window.__sidecarBaseUrl?.replace(/\/$/, "") || "";
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
        this.consoleShown = false;
        this.init();
    }

    async init() {
        await this.fetchInitialData(true);
        this.setupEventListeners();
    }

    async request(endpoint, options = {}) {
        try {
            const response = await fetch(this.baseUrl + endpoint, {
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": this.csrfToken,
                    ...(options.headers || {}),
                },
                ...options,
            });

            if (!response.ok) {
                localStorage.setItem("sidecar_authenticated", "false");

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
        const data = await this.request("/__devsquad-sidecar/data?without_users=" + (withoutUsers ? "true" : "false"), {});

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

        this.dispatch("sidecar:to:extension:data", data);
    }

    setupEventListeners() {

        window.addEventListener("sidecar:to:page:selectUser", ({ detail }) => {
            this.handleUserLogin(detail.id);
        });

        window.addEventListener("sidecar:to:page::refresh", async () => {
            await this.fetchInitialData();
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

    async handleUserLogin(userId) {
        const data = await this.request("/__devsquad-sidecar/login-as", {
            method: "POST",
            body: JSON.stringify({ user_id: userId }),
        });

        if (data.redirect) {
            window.location.href = data.redirect;
        }
    }

    async handleCommand(endpoint, payload, outputEvent) {
        const data = await this.request(endpoint, {
            method: "POST",
            body: JSON.stringify(payload),
        });

        if (data.error) {
            console.warn('Sidecar: ', data);
        }

        this.dispatch(outputEvent, data.output ?? data.error.message);
    }

    dispatch(event, detail) {
        window.dispatchEvent(new CustomEvent(event, { detail }));
    }
}

document.addEventListener("DOMContentLoaded", () => new Sidecar());
