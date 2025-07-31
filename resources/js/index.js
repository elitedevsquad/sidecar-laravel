export class Sidecar {
    constructor() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
        this.init();
    }

    async init() {
        await this.fetchInitialData();
        this.setupEventListeners();
    }

    async fetchInitialData() {
        try {
            const response = await fetch("/__devsquad-sidecar/data", {
                headers: {
                    Accept: "application/json"
                },
            });

            if (!response.ok) {
                localStorage.setItem('sidecar_authenticated', 'false');
                window.dispatchEvent(new CustomEvent("dx:to:extension:data", { detail: { statusCode: response.status } }));
                return;
            }

            const data = await response.json();

            console.log(
                "\n%cDevSquad Sidecar Enabled\n%cProject: " + data.project_name + "",
                "color:#28ef00;font-size:1em;",
                "color:#aaa;font-size:0.9em;",
            );

            window.dispatchEvent(new CustomEvent("dx:to:extension:data", { detail: data }));
        } catch (error) {
            console.error("DevPanel Bridge: Error fetching initial data:", error);
        }
    }

    setupEventListeners() {
        window.addEventListener("dx:to:page:token", async ({ detail }) => {
            const response = await fetch('__devsquad-sidecar/token', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": this.csrfToken,
                },
                body: JSON.stringify({ token: detail.token }),
            });

            if (!response.ok) {
                localStorage.setItem('sidecar_authenticated', 'false');
                alert('❌ API token is invalid or not set.');
            }

            if (response.ok) {
                localStorage.setItem('sidecar_authenticated', 'true');
                alert('🎉 API token set successfully!');
            }
        });

        window.addEventListener("dx:to:page:selectUser", ({ detail }) => {
            this.handleUserLogin(detail.id);
        });

        window.addEventListener("dx:to:page:executeCommand", ({ detail }) => {
            this.handleCommand(detail.command, "dx:to:extension:commandOutput");
        });

        window.addEventListener("dx:to:page:executeTinker", ({ detail }) => {
            this.handleCommand(detail.code, "dx:to:extension:tinkerOutput");
        });
    }

    handleUserLogin(userId) {
        this.post("/__devsquad-sidecar/login-as", { user_id: userId }).then((data) => {
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        });
    }

    handleCommand(payload, eventName) {
        const isTinker = eventName.includes("tinker");
        const endpoint = isTinker ? "/__devsquad-sidecar/execute-tinker" : "/__devsquad-sidecar/execute-command";
        const body = isTinker ? { code: payload } : { command: payload };

        this.post(endpoint, body).then((data) => {
            window.dispatchEvent(new CustomEvent(eventName, { detail: data.output }));
        });
    }

    async post(endpoint, body) {
        try {
            const response = await fetch(endpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": this.csrfToken
                },
                body: JSON.stringify(body),
            });

            if (!response.ok) {
                localStorage.setItem('sidecar_authenticated', 'false');

                return {
                    error: `Failed to post to ${endpoint}: ${response.statusText}`,
                };
            }

            return await response.json();
        } catch (error) {
            console.error(`DevPanel Bridge: Error posting to ${endpoint}:`, error);
            window.dispatchEvent(new CustomEvent("dx:to:extension:error", { detail: error.message }));
        }
    }
}
