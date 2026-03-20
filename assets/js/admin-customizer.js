(function () {

    const state = {
        calLink: "",
        type: "1",
        ui: {},
        config: {}
    };

    function updateState() {

        state.calLink = document.getElementById("calLink")?.value || "";
        state.type = document.getElementById("type")?.value || "1";

        const theme = document.getElementById("theme")?.value || "light";
        const brandColor = document.getElementById("brandColor")?.value || "#000000";
        const layout = document.getElementById("layout")?.value || "month_view";
        const hideDetails = document.getElementById("hideDetails")?.checked || false;
        const slotsMobile = document.getElementById("slotsMobile")?.checked || false;
        const disableScroll = document.getElementById("disableScroll")?.checked || false;

        state.ui = {
            theme,
            cssVarsPerTheme: { [theme]: { "cal-brand": brandColor } },
            hideEventTypeDetails: hideDetails,
            layout
        };

        state.config = {
            layout,
            useSlotsViewOnSmallScreen: slotsMobile,
            disableMobileScroll: disableScroll
        };
    }

    function normalizeCalLink(input) {

        const trimmed = input.trim();

        if (!trimmed) return { url: "", instance: "" };

        if (trimmed.startsWith("https://cal.com/")) {

            const path = new URL(trimmed).pathname.replace(/^\/|\/$/g, "");
            return { url: "/" + path, instance: "" };
        }

        if (/^https?:\/\//i.test(trimmed)) {

            const urlObj = new URL(trimmed);
            const path = urlObj.pathname.replace(/^\/|\/$/g, "");

            return { url: "/" + path, instance: urlObj.origin };
        }

        return { url: "/" + trimmed.replace(/^\/|\/$/g, ""), instance: "" };
    }

    function cleanupPreviousEmbed() {
        document.querySelectorAll("cal-floating-button").forEach(el => el.remove());

        const preview = document.getElementById("cal-preview");
        if (preview) preview.innerHTML = "";
    }

    function removeLoading() {
        const loader = document.querySelector("#cal-preview .cal-loading");

        if (loader) loader.remove();
    }

    function generateShortcode() {

        updateState();
        cleanupPreviousEmbed();

        const uiString = JSON.stringify(state.ui).replace(/'/g, "&apos;");
        const configString = JSON.stringify(state.config).replace(/'/g, "&apos;");
        const shortcode = `[cal_custom url="${state.calLink}" type="${state.type}" ui='${uiString}' config='${configString}']`;

        const output = document.getElementById("output");

        if (output) output.value = shortcode;

        const preview = document.getElementById("preview");

        preview.innerHTML = `
            <div id="cal-preview">
                <div class="cal-loading">Loading preview...</div>
            </div>
        `;

        const container = document.getElementById("cal-preview");

        if (!state.calLink) {
            container.innerHTML = `<div class="cal-placeholder">Enter a Cal link to preview</div>`;
            return;
        }

        if (state.type === "2") {
            container.innerHTML = `<div class="modal-placeholder">Click to preview modal</div>`;
        }

        renderCalEmbed("cal-preview");
    }

    function renderCalEmbed(containerId) {

        const ns = "cal_custom";
        const normalized = normalizeCalLink(state.calLink);

        if (!normalized.url) return;

        const data = {
            elementId: containerId,
            type: parseInt(state.type, 10),
            calLink: normalized.url,
            config: state.config,
            ui: state.ui,
            customCalInstance: normalized.instance || ""
        };

        window.calcomScriptUrl = data.customCalInstance
            ? data.customCalInstance.replace(/\/$/, "") + "/embed/embed.js"
            : "https://cal.com/embed.js";

        const api = Cal("init", ns, data.customCalInstance ? { origin: data.customCalInstance } : {});
        const config = Object.assign({ calLink: data.calLink }, data.config);

        if (Object.keys(data.ui).length) {
            api("ui", data.ui);
        }

        if (data.type === 1) {

            config.elementOrSelector = "#" + containerId;
            api("inline", config);

            setTimeout(removeLoading, 2000);
        }

        if (data.type === 2) {
            const el = document.getElementById(containerId);

            if (el) el.addEventListener("click", () => api("modal", config));
        }

        if (data.type === 3) {
            api("floatingButton", config);

            setTimeout(removeLoading, 2000);
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll("#cal-customizer input, #cal-customizer select").forEach(el => {
            el.addEventListener("input", updateState);
            el.addEventListener("change", updateState);
        });

        document.getElementById("generate")?.addEventListener("click", generateShortcode);

        updateState();
    });
})();