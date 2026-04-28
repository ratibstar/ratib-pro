(() => {
    "use strict";

    const TAB_ID = "coreai-navbar-tab";
    const TAB_LABEL = "CoreAI";
    const TARGET_URL = "/coreai/index.php";

    /**
     * Try to find a navbar-like host container dynamically.
     * محاولة اكتشاف شريط تنقل مناسب بشكل ديناميكي.
     */
    function findNavbarHost() {
        const selectors = [
            ".navbar-nav",
            ".navbar",
            "nav ul",
            "nav",
            ".topbar-nav"
        ];

        for (const selector of selectors) {
            const element = document.querySelector(selector);
            if (element) {
                return element;
            }
        }
        return null;
    }

    /**
     * Inject CoreAI tab button once (no system file edits needed).
     * حقن زر CoreAI مرة واحدة بدون تعديل أي ملف نظام.
     */
    function injectCoreAITab() {
        if (document.getElementById(TAB_ID)) {
            return;
        }

        const host = findNavbarHost();
        if (!host) {
            return;
        }

        const button = document.createElement("button");
        button.type = "button";
        button.id = TAB_ID;
        button.textContent = TAB_LABEL;
        button.className = "coreai-tab-button";
        button.addEventListener("click", () => {
            window.open(TARGET_URL, "_blank", "noopener,noreferrer");
        });

        const inList = host.tagName.toLowerCase() === "ul";
        if (inList) {
            const li = document.createElement("li");
            li.appendChild(button);
            host.appendChild(li);
            return;
        }

        host.appendChild(button);
    }

    /**
     * Observe DOM for late-loaded navbars.
     * مراقبة تغييرات DOM لدعم الشريط الذي يظهر لاحقا.
     */
    const observer = new MutationObserver(() => {
        injectCoreAITab();
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true
    });

    injectCoreAITab();
})();
