<footer class="footer text-center text-sm-start d-print-none">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <div class="card mb-0 border-bottom-0 rounded-bottom-0">
                                <div class="card-body">
                                    <p class="text-muted mb-0">
                                        ©
                                        <script> document.write(new Date().getFullYear()) </script>
                                        Silky Saree
                                        <span class="text-muted d-none d-sm-inline-block float-end">
                                            Developed with
                                            <i class="iconoir-heart-solid text-danger align-middle"></i>
                                            by <a href="https://coida.in/" target="_blank">Coida Technologies</a></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>

            <style>
                /* SweetAlert Theme & Dark Mode Integration */
                .swal2-popup {
                    border-radius: 10px !important;
                    font-family: inherit !important;
                }
                [data-bs-theme="dark"] .swal2-popup {
                    background: #1e2430 !important;
                    color: #e2e8f0 !important;
                    border: 1px solid #334155 !important;
                }
                [data-bs-theme="dark"] .swal2-title,
                [data-bs-theme="dark"] .swal2-html-container {
                    color: #f1f5f9 !important;
                }
            </style>

            <script>
                // Global Theme Confirm Dialog Helper using SweetAlert2
                window.themeConfirm = function(options, onConfirm, onCancel) {
                    let title = 'Are you sure?';
                    let text = '';
                    let confirmBtnText = 'Yes, proceed!';
                    let confirmBtnColor = '#e03e2d';
                    let cancelBtnText = 'Cancel';
                    let cancelBtnColor = '#6c757d';
                    let icon = 'warning';

                    if (typeof options === 'string') {
                        text = options;
                        if (/delete|remove|trash|clear/i.test(options)) {
                            confirmBtnText = 'Yes, delete it!';
                        }
                    } else if (typeof options === 'object' && options !== null) {
                        title = options.title || title;
                        text = options.text || options.message || '';
                        confirmBtnText = options.confirmButtonText || (options.isDelete ? 'Yes, delete it!' : confirmBtnText);
                        confirmBtnColor = options.confirmButtonColor || confirmBtnColor;
                        cancelBtnText = options.cancelButtonText || cancelBtnText;
                        cancelBtnColor = options.cancelButtonColor || cancelBtnColor;
                        icon = options.icon || icon;
                    }

                    if (typeof Swal !== 'undefined') {
                        return Swal.fire({
                            title: title,
                            text: text,
                            icon: icon,
                            showCancelButton: true,
                            confirmButtonColor: confirmBtnColor,
                            cancelButtonColor: cancelBtnColor,
                            confirmButtonText: confirmBtnText,
                            cancelButtonText: cancelBtnText,
                            reverseButtons: true,
                            focusCancel: true
                        }).then(function(result) {
                            if (result.isConfirmed) {
                                if (typeof onConfirm === 'function') onConfirm();
                                return true;
                            } else {
                                if (typeof onCancel === 'function') onCancel();
                                return false;
                            }
                        });
                    } else {
                        if (confirm(text || title)) {
                            if (typeof onConfirm === 'function') onConfirm();
                            return Promise.resolve(true);
                        } else {
                            if (typeof onCancel === 'function') onCancel();
                            return Promise.resolve(false);
                        }
                    }
                };

                // Global capturing click listener to intercept any confirm triggers across the project
                document.addEventListener('click', function(e) {
                    const clickable = e.target.closest('a, button, [onclick], [data-confirm]');
                    if (!clickable) return;

                    // 1. Explicit data-confirm attribute
                    const dataConfirm = clickable.getAttribute('data-confirm') || clickable.getAttribute('data-confirm-text');
                    if (dataConfirm) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        window.themeConfirm(dataConfirm, function() {
                            if (clickable.tagName.toLowerCase() === 'a' && clickable.href && !clickable.href.startsWith('javascript:')) {
                                window.location.href = clickable.href;
                            } else if (clickable.type === 'submit' && clickable.form) {
                                clickable.form.submit();
                            }
                        });
                        return;
                    }

                    // 2. Inline onclick attribute containing confirm(...)
                    const onclickAttr = clickable.getAttribute('onclick');
                    if (onclickAttr && /confirm\s*\(/i.test(onclickAttr)) {
                        e.preventDefault();
                        e.stopImmediatePropagation();

                        let message = 'Are you sure you want to proceed?';
                        const match = onclickAttr.match(/confirm\s*\(\s*(['"`])(.*?)\1\s*\)/s);
                        if (match && match[2]) {
                            message = match[2].replace(/\\'/g, "'").replace(/\\"/g, '"');
                        }

                        window.themeConfirm(message, function() {
                            if (clickable.tagName.toLowerCase() === 'a' && clickable.href && !clickable.href.startsWith('javascript:')) {
                                window.location.href = clickable.href;
                            } else if (clickable.type === 'submit' && clickable.form) {
                                clickable.form.submit();
                            } else {
                                const origOnclick = onclickAttr;
                                clickable.removeAttribute('onclick');
                                clickable.click();
                                setTimeout(function() {
                                    clickable.setAttribute('onclick', origOnclick);
                                }, 500);
                            }
                        });
                        return;
                    }
                }, true);

                // Make flag global so custom save scripts can bypass it
                window.hasUnsavedChanges = false;
                
                document.addEventListener('DOMContentLoaded', function() {
                    // Attach change listeners to all forms
                    document.querySelectorAll('form').forEach(form => {
                        if (form.classList.contains('no-warn')) return;
                        
                        form.addEventListener('input', () => window.hasUnsavedChanges = true);
                        form.addEventListener('change', () => window.hasUnsavedChanges = true);
                        
                        form.addEventListener('submit', () => window.hasUnsavedChanges = false);
                    });
                    
                    // 1. Handle closing tabs or refreshing (Browser strictly forces native dialog here)
                    window.addEventListener('beforeunload', function (e) {
                        if (window.hasUnsavedChanges) {
                            e.preventDefault();
                            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                            return e.returnValue;
                        }
                    });

                    // 2. Intercept internal link clicks to use theme's SweetAlert instead of native
                    document.addEventListener('click', function(e) {
                        let link = e.target.closest('a');
                        
                        if (link && link.href && !link.href.includes('#') && !link.href.startsWith('javascript:')) {
                            if (link.origin === window.location.origin && link.target !== '_blank' && window.hasUnsavedChanges) {
                                e.preventDefault();
                                
                                window.themeConfirm({
                                    title: 'Unsaved Changes!',
                                    text: 'You have unsaved changes. Are you sure you want to leave this page?',
                                    confirmButtonText: 'Yes, leave page',
                                    cancelButtonText: 'No, stay',
                                    confirmButtonColor: '#3085d6',
                                    cancelButtonColor: '#d33'
                                }, function() {
                                    window.hasUnsavedChanges = false;
                                    window.location.href = link.href;
                                });
                            }
                        }
                    });

                    // 3. Override native alert to use SweetAlert if available
                    if (typeof Swal !== 'undefined') {
                        window.nativeAlert = window.alert;
                        window.alert = function(message) {
                            Swal.fire({
                                text: message,
                                icon: 'info',
                                confirmButtonColor: '#3085d6'
                            });
                        };
                    }
                });
            </script>