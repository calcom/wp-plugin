(function () {
    const state = {
        calLink: "",
        type: "1",
        prefill: false,
        utm: "",
        ui: {},
        config: {}
    };

    function refreshUI() {
        const ns = "cal_custom";

        if (!window.Cal || !Cal.ns || !Cal.ns[ns]) return;

        const api = Cal.ns[ns];

        if (api && state.ui && Object.keys(state.ui).length) {
            api("ui", state.ui);
        }
    }

    // update state from form inputs
    function updateState() {
        const oldType = state.type;
        const oldLink = state.calLink;

        state.calLink = document.getElementById("calLink")?.value.trim() || "";
        state.type = document.getElementById("type")?.value || "1";
        state.prefill = document.getElementById("prefill")?.checked || false;
        state.utm = document.getElementById("utm")?.value || "";

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

        // update live shortcode in output
        updateShortcodeOutput();

        // reset preview if calLink changed or type changed
        if (state.calLink !== oldLink || state.type !== oldType) {
            cleanupPreviousEmbed();
        }

        if (state.calLink) refreshUI();

        const container = document.getElementById("cal-preview");

        if (container) {
            if (!state.calLink) {
                container.innerHTML = `<div class="cal-placeholder">Start customizing</div>`;
            } else if (state.type === "2") {
                container.innerHTML = `<div class="modal-placeholder">Click to preview modal</div>`;
            }
        }
    }

    // update the shortcode displayed in the textarea
    function updateShortcodeOutput() {
        const output = document.getElementById("output");

        if (!output) return;

        const uiString = JSON.stringify(state.ui).replace(/'/g, "&apos;");
        const configString = JSON.stringify(state.config).replace(/'/g, "&apos;");

        const shortcode = `[cal_custom url="${state.calLink}" type="${state.type}" prefill="${state.prefill}" utm="${state.utm}" ui='${uiString}' config='${configString}']`;
        output.value = shortcode;
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
        document.querySelectorAll("cal-modal-box").forEach(el => el.remove());

        const preview = document.getElementById("cal-preview");

        if (preview) preview.innerHTML = "";
    }

    function showLoading(containerId) {
        const container = document.getElementById(containerId);

        if (!container) return;

        let loader = container.querySelector(".cal-loading");

        if (!loader) {

            loader = document.createElement("div");
            loader.className = "cal-loading";
            loader.textContent = "Loading preview...";

            container.appendChild(loader);
        }
    }

    function removeLoading() {
        const loader = document.querySelector("#cal-preview .cal-loading");

        if (loader) loader.remove();
    }

    function renderCalEmbed(containerId) {
        if (!state.calLink) return;

        const ns = "cal_custom";
        const normalized = normalizeCalLink(state.calLink);

        if (!normalized.url) return;

        const data = {
            elementId: containerId,
            type: parseInt(state.type, 10),
            calLink: normalized.url,
            prefill: state.prefill,
            utm: state.utm,
            config: state.config,
            ui: state.ui,
            customCalInstance: normalized.instance || ""
        };

        window.calcomScriptUrl = data.customCalInstance
            ? data.customCalInstance.replace(/\/$/, "") + "/embed/embed.js"
            : "https://cal.com/embed.js";

        const api = Cal("init", ns, data.customCalInstance ? { origin: data.customCalInstance } : {});
        const config = Object.assign({ calLink: data.calLink }, data.config);

        if (Object.keys(data.ui).length) api("ui", data.ui);

        showLoading(containerId);

        if (data.type === 1) {
            config.elementOrSelector = "#" + containerId;

            api("inline", config);
            setTimeout(removeLoading, 1500);
        }

        if (data.type === 2) {
            const el = document.getElementById(containerId);

            if (el && !el.dataset.modalListener) { // prevent multiple listeners

                el.dataset.modalListener = "true";

                el.addEventListener("click", () => {
                    const modalConfig = Object.assign({ calLink: data.calLink }, data.config);

                    if (Object.keys(data.ui).length) modalConfig.ui = data.ui;

                    api("modal", modalConfig);
                });
            }
        }

        if (data.type === 3) {
            const floatingConfig = Object.assign({ calLink: data.calLink }, data.config);

            if (Object.keys(data.ui).length) floatingConfig.ui = data.ui;

            api("floatingButton", floatingConfig);
            setTimeout(removeLoading, 1500);
        }
    }

    function initializePreview() {
        const preview = document.getElementById("preview");

        if (!preview) return;

        preview.innerHTML = `<div id="cal-preview"></div>`;
    }

    function debounce(func, delay = 1000) {
        let timer;

        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => func.apply(this, args), delay);
        };
    }

    function fallbackCopy(el) {
        el.select();
        el.setSelectionRange(0, 99999);

        try {

            document.execCommand("copy");
            alert("Shortcode copied!");
        } catch (err) {
            alert("Unable to copy, please copy manually.");
        }
    }

    // initialize customizer event listeners
    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll("#cal-customizer input, #cal-customizer select").forEach(el => {

            el.addEventListener("input", debounce(() => {
                updateState();

                if (state.calLink && state.type !== "2") renderCalEmbed("cal-preview");
            }));

            el.addEventListener("change", () => {
                updateState();
                if (state.calLink) renderCalEmbed("cal-preview");
            });
        });

        const copyBtn = document.getElementById("copy");

        if (copyBtn) {

            copyBtn.textContent = "Copy Shortcode";
            copyBtn.addEventListener("click", () => {

                const output = document.getElementById("output");
        
                if (output) {
        
                    if (navigator.clipboard && navigator.clipboard.writeText) {
        
                        navigator.clipboard.writeText(output.value).then(() => {
                            alert("Shortcode copied!");
                        }).catch(() => fallbackCopy(output));
                    } else {
                        fallbackCopy(output);
                    }
                }
            });
        }

        initializePreview();
        updateState();
    });
})();