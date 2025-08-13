(function() {
    document.addEventListener("DOMContentLoaded", function() {
        var formId = window.wpjbPayfastFormId || "wpjb-payfast-auto-submit";
        var form = document.getElementById(formId);
        if(form) {
            setTimeout(function() {
                try { form.submit(); } catch(e) {}
            }, 100);
        }
    });
})();

