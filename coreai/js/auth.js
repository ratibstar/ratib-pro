(() => {
    "use strict";

    const loginForm = document.getElementById("coreai-login-form");
    const registerForm = document.getElementById("coreai-register-form");
    const statusNode = document.getElementById("coreai-auth-status");

    if (!loginForm || !registerForm || !statusNode) {
        return;
    }

    function setStatus(message, isError = false) {
        statusNode.textContent = message;
        statusNode.style.color = isError ? "#ff9aa2" : "#9ecbff";
    }

    async function submit(action, username, password) {
        const response = await fetch(`php/account.php?action=${encodeURIComponent(action)}`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ username, password }),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.ok !== true) {
            throw new Error(payload.error || `Failed to ${action}.`);
        }
        return payload;
    }

    loginForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        const username = String(document.getElementById("coreai-login-username")?.value || "").trim();
        const password = String(document.getElementById("coreai-login-password")?.value || "");
        try {
            setStatus("Signing in...");
            await submit("login", username, password);
            setStatus("Login successful. Redirecting...");
            window.location.reload();
        } catch (error) {
            setStatus(error instanceof Error ? error.message : "Login failed.", true);
        }
    });

    registerForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        const username = String(document.getElementById("coreai-register-username")?.value || "").trim();
        const password = String(document.getElementById("coreai-register-password")?.value || "");
        try {
            setStatus("Creating account...");
            await submit("register", username, password);
            setStatus("Account created. Redirecting...");
            window.location.reload();
        } catch (error) {
            setStatus(error instanceof Error ? error.message : "Registration failed.", true);
        }
    });
})();
