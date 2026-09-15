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

            <!-- Global Unsaved Changes Warning -->
            <script>
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
                        // Find the closest anchor tag that was clicked
                        let link = e.target.closest('a');
                        
                        // If it's a link, it has an href, it's not a hash link, and we have unsaved changes
                        if (link && link.href && !link.href.includes('#') && !link.href.startsWith('javascript:')) {
                            // Check if it's an internal link (same origin) and not target="_blank"
                            if (link.origin === window.location.origin && link.target !== '_blank' && window.hasUnsavedChanges) {
                                e.preventDefault(); // Stop immediate navigation
                                
                                // Check if SweetAlert is available
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        title: 'Unsaved Changes!',
                                        text: "You have unsaved changes. Are you sure you want to leave this page?",
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonColor: '#3085d6',
                                        cancelButtonColor: '#d33',
                                        confirmButtonText: 'Yes, leave page',
                                        cancelButtonText: 'No, stay'
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            // Allow navigation
                                            window.hasUnsavedChanges = false;
                                            window.location.href = link.href;
                                        }
                                    });
                                } else {
                                    // Fallback if Swal is not loaded for some reason
                                    if (confirm('You have unsaved changes. Are you sure you want to leave this page?')) {
                                        window.hasUnsavedChanges = false;
                                        window.location.href = link.href;
                                    }
                                }
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
                        
                        // Note: We cannot override window.confirm directly because native confirm is synchronous 
                        // and halts execution, whereas SweetAlert is asynchronous. We provided the SweetAlert 
                        // implementation directly in the link interceptor above!
                    }
                });
            </script>