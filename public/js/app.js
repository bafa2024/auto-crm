
        // Page Navigation - Fixed to prevent page reload
        function showPage(pageId) {
            // Hide all pages
            document.querySelectorAll('.page-content').forEach(page => {
                page.classList.remove('active');
            });
            
            // Show selected page
            const targetPage = document.getElementById(pageId);
            if (targetPage) {
                targetPage.classList.add('active');
            }
            
            // Initialize charts if showing dashboard
            if (pageId === 'dashboard-page') {
                setTimeout(initializeCharts, 100);
            }
        }

        // Dashboard Section Navigation - Fixed to prevent issues
        function showDashboardSection(sectionId, linkElement) {
            // Hide all dashboard sections
            document.querySelectorAll('.dashboard-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected section
            const targetSection = document.getElementById(sectionId + '-section');
            if (targetSection) {
                targetSection.classList.add('active');
            }

            // Update sidebar active state
            if (linkElement) {
                document.querySelectorAll('.sidebar-link').forEach(link => {
                    link.classList.remove('active');
                });
                linkElement.classList.add('active');
            }
        }

        // Toggle Sidebar (Mobile)
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Initialize Charts
        function initializeCharts() {
            // Call Volume Chart
            const callVolumeCtx = document.getElementById('callVolumeChart');
            if (callVolumeCtx && typeof Chart !== 'undefined') {
                new Chart(callVolumeCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                        datasets: [{
                            label: 'Calls Made',
                            data: [245, 312, 289, 342, 365, 180, 120],
                            borderColor: '#5B5FDE',
                            backgroundColor: 'rgba(91, 95, 222, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }

            // Agent Status Chart
            const agentStatusCtx = document.getElementById('agentStatusChart');
            if (agentStatusCtx && typeof Chart !== 'undefined') {
                new Chart(agentStatusCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Available', 'On Call', 'Break', 'Offline'],
                        datasets: [{
                            data: [12, 8, 3, 2],
                            backgroundColor: ['#10B981', '#EF4444', '#F59E0B', '#6B7280']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }

            // Call Volume Trend Chart (Analytics)
            const callVolumeTrendCtx = document.getElementById('callVolumeTrendChart');
            if (callVolumeTrendCtx && typeof Chart !== 'undefined') {
                new Chart(callVolumeTrendCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'Calls Made',
                            data: [8500, 9200, 8800, 10200, 11500, 10800],
                            borderColor: '#5B5FDE',
                            backgroundColor: 'rgba(91, 95, 222, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // Call Outcomes Chart (Analytics)
            const callOutcomesCtx = document.getElementById('callOutcomesChart');
            if (callOutcomesCtx && typeof Chart !== 'undefined') {
                new Chart(callOutcomesCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Connected', 'Voicemail', 'No Answer', 'Busy', 'Wrong Number'],
                        datasets: [{
                            data: [45, 25, 20, 5, 5],
                            backgroundColor: ['#10B981', '#F59E0B', '#EF4444', '#6B7280', '#8B5CF6']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true
                                }
                            }
                        }
                    }
                });
            }
        }

        // Enhanced form submissions and event handlers
        document.addEventListener('DOMContentLoaded', function() {
            // Login form
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    showPage('dashboard-page');
                });
            }

            // Signup form
            const signupForm = document.getElementById('signupForm');
            if (signupForm) {
                signupForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    showPage('dashboard-page');
                });
            }

            // Prevent all anchor tags from reloading the page
            document.addEventListener('click', function(e) {
                if (e.target.tagName === 'A' && e.target.getAttribute('href') === '#') {
                    e.preventDefault();
                }
            });

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Initialize popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });

            // Add event listeners for dynamic content
            document.addEventListener('click', function(e) {
                // Dialer mode selection
                if (e.target.closest('.dialer-mode-card')) {
                    document.querySelectorAll('.dialer-mode-card').forEach(card => {
                        card.classList.remove('active');
                    });
                    e.target.closest('.dialer-mode-card').classList.add('active');
                }

                // Voice option selection
                if (e.target.closest('.voice-option-card')) {
                    document.querySelectorAll('.voice-option-card').forEach(card => {
                        card.classList.remove('active');
                    });
                    e.target.closest('.voice-option-card').classList.add('active');
                }

                // CRM card selection
                if (e.target.closest('.crm-card')) {
                    document.querySelectorAll('.crm-card').forEach(card => {
                        card.classList.remove('active');
                    });
                    e.target.closest('.crm-card').classList.add('active');
                }
            });

            // Form validation and submission handlers
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    // Add form validation logic here
                    console.log('Form submitted:', form.id);
                });
            });

            // Modal event handlers
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('show.bs.modal', function() {
                    console.log('Modal opening:', modal.id);
                });
                
                modal.addEventListener('hidden.bs.modal', function() {
                    console.log('Modal closed:', modal.id);
                });
            });

            // Initialize charts when dashboard is shown
            if (document.getElementById('dashboard-page').classList.contains('active')) {
                setTimeout(initializeCharts, 100);
            }
        });

        // Utility functions
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Global search functionality
        function performGlobalSearch(query) {
            if (!query.trim()) return;
            
            // This would typically search across contacts, campaigns, etc.
            console.log('Searching for:', query);
            showNotification(`Searching for: ${query}`, 'info');
        }

        // Export functionality
        function exportData(type, format = 'csv') {
            console.log(`Exporting ${type} as ${format}`);
            showNotification(`Exporting ${type} data...`, 'success');
        }

        // Settings tab navigation
        function showSettingsTab(tabId) {
            // Hide all settings tabs
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            const targetTab = document.getElementById(tabId + '-settings');
            if (targetTab) {
                targetTab.classList.add('active');
            }

            // Update navigation active state
            document.querySelectorAll('.list-group-item').forEach(item => {
                item.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        // Bulk Upload Functions
        function handleFileUpload(input) {
            const file = input.files[0];
            if (file) {
                // Validate file type and size
                const validTypes = ['.csv', '.xlsx', '.xls'];
                const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
                
                if (!validTypes.includes(fileExtension)) {
                    showNotification('Please select a valid file type (CSV, Excel)', 'error');
                    return;
                }
                
                if (file.size > 10 * 1024 * 1024) { // 10MB limit
                    showNotification('File size must be less than 10MB', 'error');
                    return;
                }
                
                // Simulate file processing
                showNotification('File uploaded successfully!', 'success');
                updateProgress(25);
                
                // Move to next step
                setTimeout(() => {
                    showStep(2);
                }, 1000);
            }
        }

        function showStep(stepNumber) {
            // Hide all steps
            document.querySelectorAll('.upload-step').forEach(step => {
                step.style.display = 'none';
            });
            
            // Show current step
            const currentStep = document.getElementById('step' + stepNumber);
            if (currentStep) {
                currentStep.style.display = 'block';
            }
            
            // Update progress bar
            const progress = (stepNumber - 1) * 33.33;
            updateProgress(progress);
        }

        function previousStep() {
            const currentStep = getCurrentStep();
            if (currentStep > 1) {
                showStep(currentStep - 1);
            }
        }

        function getCurrentStep() {
            for (let i = 1; i <= 4; i++) {
                const step = document.getElementById('step' + i);
                if (step && step.style.display !== 'none') {
                    return i;
                }
            }
            return 1;
        }

        function updateProgress(percentage) {
            const progressBar = document.getElementById('uploadProgress');
            if (progressBar) {
                progressBar.style.width = percentage + '%';
                progressBar.textContent = Math.round(percentage) + '%';
            }
        }

        function validateMapping() {
            // Simulate validation process
            showNotification('Validating field mapping...', 'info');
            
            setTimeout(() => {
                showNotification('Field mapping validated successfully!', 'success');
                showStep(3);
            }, 2000);
        }

        function proceedToImport() {
            showStep(4);
            
            // Simulate import process
            let progress = 0;
            const importProgress = document.getElementById('importProgress');
            
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress >= 100) {
                    progress = 100;
                    clearInterval(interval);
                    
                    setTimeout(() => {
                        showNotification('Import completed successfully!', 'success');
                        // Reset to step 1
                        setTimeout(() => {
                            showStep(1);
                            updateProgress(0);
                        }, 2000);
                    }, 1000);
                }
                
                if (importProgress) {
                    importProgress.style.width = progress + '%';
                    importProgress.textContent = Math.round(progress) + '%';
                }
            }, 500);
        }

        function fixErrors() {
            showNotification('Opening error correction interface...', 'info');
            // This would typically open a modal or interface to fix validation errors
        }

        function downloadTemplate() {
            // Create a sample CSV template
            const csvContent = "First Name,Last Name,Phone Number,Email,Company,Job Title,Notes\nJohn,Smith,+1 (555) 123-4567,john.smith@email.com,ABC Corp,Sales Manager,Sample contact\nJane,Doe,+1 (555) 987-6543,jane.doe@email.com,XYZ Inc,Director,Another sample";
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'contact_template.csv';
            a.click();
            window.URL.revokeObjectURL(url);
            
            showNotification('Template downloaded successfully!', 'success');
        }

        function showUploadHistory() {
            showNotification('Opening upload history...', 'info');
            // This would typically open a modal showing upload history
        }

        // Drag and drop functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropZone = document.querySelector('.border-dashed');
            
            if (dropZone) {
                dropZone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    dropZone.style.backgroundColor = '#e9ecef';
                    dropZone.style.borderColor = '#5B5FDE';
                });
                
                dropZone.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    dropZone.style.backgroundColor = '#f8f9fa';
                    dropZone.style.borderColor = '#dee2e6';
                });
                
                dropZone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    dropZone.style.backgroundColor = '#f8f9fa';
                    dropZone.style.borderColor = '#dee2e6';
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        const fileInput = document.getElementById('contactFile');
                        fileInput.files = files;
                        handleFileUpload(fileInput);
                    }
                });
            }
        });

        // Password visibility toggle function
        function togglePasswordVisibility(button) {
            const inputGroup = button.closest('.input-group');
            const passwordInput = inputGroup.querySelector('input[type="password"]');
            const icon = button.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        // Voicemail Detection Functions
        function updateConfidenceThreshold() {
            const slider = document.getElementById('confidenceThreshold');
            const display = slider.nextElementSibling;
            if (slider && display) {
                display.textContent = slider.value + '%';
            }
        }

        function testVoicemailDetection() {
            showNotification('Starting voicemail detection test...', 'info');
            
            // Simulate test process
            setTimeout(() => {
                showNotification('Test completed! Accuracy: 98.5%', 'success');
            }, 3000);
        }

        function calibrateVoicemailSystem() {
            showNotification('Starting system calibration...', 'info');
            
            // Simulate calibration process
            let progress = 0;
            const interval = setInterval(() => {
                progress += 10;
                if (progress >= 100) {
                    clearInterval(interval);
                    showNotification('System calibration completed successfully!', 'success');
                }
            }, 500);
        }

        function exportVoicemailData() {
            showNotification('Preparing data export...', 'info');
            
            // Simulate export process
            setTimeout(() => {
                const csvContent = "Date,Phone Number,Detection Result,Confidence,Duration,Action\n2024-01-15 14:30,+1 (555) 123-4567,Human,96.2%,2.1s,Connected\n2024-01-15 14:28,+1 (555) 987-6543,Voicemail,98.7%,1.8s,Left Message";
                
                const blob = new Blob([csvContent], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'voicemail_detection_data.csv';
                a.click();
                window.URL.revokeObjectURL(url);
                
                showNotification('Data exported successfully!', 'success');
            }, 2000);
        }

        function resetVoicemailSettings() {
            if (confirm('Are you sure you want to reset all voicemail detection settings to default?')) {
                showNotification('Resetting settings...', 'info');
                
                // Reset form elements
                const confidenceSlider = document.getElementById('confidenceThreshold');
                if (confidenceSlider) {
                    confidenceSlider.value = 85;
                    updateConfidenceThreshold();
                }
                
                // Reset other form elements to defaults
                setTimeout(() => {
                    showNotification('Settings reset to default values!', 'success');
                }, 1000);
            }
        }

        // Initialize voicemail detection event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Confidence threshold slider
            const confidenceSlider = document.getElementById('confidenceThreshold');
            if (confidenceSlider) {
                confidenceSlider.addEventListener('input', updateConfidenceThreshold);
            }

            // Detection method radio buttons
            const detectionMethods = document.querySelectorAll('input[name="detectionMethod"]');
            detectionMethods.forEach(method => {
                method.addEventListener('change', function() {
                    const accuracy = this.id === 'aiDetection' ? '98.5%' : '92%';
                    const statusBadge = document.querySelector('.badge.bg-success');
                    if (statusBadge) {
                        statusBadge.textContent = accuracy + ' Accuracy';
                    }
                });
            });
            
            // Highlight voicemail detection link on page load
            setTimeout(() => {
                const voicemailLink = document.querySelector('.voicemail-detection-link');
                if (voicemailLink) {
                    voicemailLink.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    voicemailLink.style.animation = 'pulse 2s infinite';
                }
            }, 1000);
        });
        
        // Contact Support Functions
        function openLiveChat() {
            showNotification('Opening live chat...', 'info');
            // This would typically open a live chat widget
            setTimeout(() => {
                showNotification('Live chat connected! A support agent will be with you shortly.', 'success');
            }, 2000);
        }

        function createTicket() {
            showNotification('Creating new support ticket...', 'info');
            // This would typically open a modal or form for creating tickets
            setTimeout(() => {
                showNotification('Ticket created successfully! Ticket #TKT-2024-004', 'success');
            }, 1500);
        }

        function viewAllTickets() {
            showNotification('Loading all tickets...', 'info');
            // This would typically show all tickets in a modal or new page
            setTimeout(() => {
                showNotification('All tickets loaded successfully!', 'success');
            }, 1000);
        }

        function searchKnowledgeBase() {
            showNotification('Opening knowledge base search...', 'info');
            // This would typically open a search interface
            setTimeout(() => {
                showNotification('Knowledge base search ready!', 'success');
            }, 1000);
        }

        function refreshTickets() {
            showNotification('Refreshing tickets...', 'info');
            // This would typically reload ticket data
            setTimeout(() => {
                showNotification('Tickets refreshed successfully!', 'success');
            }, 1000);
        }

        function viewTicket(ticketId) {
            showNotification(`Opening ticket ${ticketId}...`, 'info');
            // This would typically open a modal with ticket details
            setTimeout(() => {
                showNotification(`Ticket ${ticketId} details loaded!`, 'success');
            }, 1000);
        }

        function replyTicket(ticketId) {
            showNotification(`Opening reply form for ticket ${ticketId}...`, 'info');
            // This would typically open a reply form
            setTimeout(() => {
                showNotification(`Reply form ready for ticket ${ticketId}!`, 'success');
            }, 1000);
        }

        function reopenTicket(ticketId) {
            if (confirm(`Are you sure you want to reopen ticket ${ticketId}?`)) {
                showNotification(`Reopening ticket ${ticketId}...`, 'info');
                setTimeout(() => {
                    showNotification(`Ticket ${ticketId} reopened successfully!`, 'success');
                }, 1000);
            }
        }

        function saveDraft() {
            showNotification('Saving draft...', 'info');
            // This would typically save the current form as a draft
            setTimeout(() => {
                showNotification('Draft saved successfully!', 'success');
            }, 1000);
        }

        function viewKnowledgeBase() {
            showNotification('Opening knowledge base...', 'info');
            // This would typically open the knowledge base
            setTimeout(() => {
                showNotification('Knowledge base loaded!', 'success');
            }, 1000);
        }

        function scheduleCall() {
            showNotification('Opening call scheduler...', 'info');
            // This would typically open a calendar/scheduler interface
            setTimeout(() => {
                showNotification('Call scheduler ready!', 'success');
            }, 1000);
        }
        
        // Add pulse animation for voicemail detection link
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
                70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
                100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
            }
        `;
        document.head.appendChild(style);

        // Contact Support Functions
        function openLiveChat() {
            showNotification('Opening live chat...', 'info');
            // This would typically open a live chat widget
            setTimeout(() => {
                showNotification('Live chat connected! A support agent will be with you shortly.', 'success');
            }, 2000);
        }

        function createTicket() {
            showNotification('Creating new support ticket...', 'info');
            // This would typically open a modal or form for creating tickets
            setTimeout(() => {
                showNotification('Ticket created successfully! Ticket #TKT-2024-004', 'success');
            }, 1500);
        }

        function viewAllTickets() {
            showNotification('Loading all tickets...', 'info');
            // This would typically show all tickets in a modal or new page
            setTimeout(() => {
                showNotification('All tickets loaded successfully!', 'success');
            }, 1000);
        }

        function searchKnowledgeBase() {
            showNotification('Opening knowledge base search...', 'info');
            // This would typically open a search interface
            setTimeout(() => {
                showNotification('Knowledge base search ready!', 'success');
            }, 1000);
        }

        function refreshTickets() {
            showNotification('Refreshing tickets...', 'info');
            // This would typically reload ticket data
            setTimeout(() => {
                showNotification('Tickets refreshed successfully!', 'success');
            }, 1000);
        }

        function viewTicket(ticketId) {
            showNotification(`Opening ticket ${ticketId}...`, 'info');
            // This would typically open a modal with ticket details
            setTimeout(() => {
                showNotification(`Ticket ${ticketId} details loaded!`, 'success');
            }, 1000);
        }

        function replyTicket(ticketId) {
            showNotification(`Opening reply form for ticket ${ticketId}...`, 'info');
            // This would typically open a reply form
            setTimeout(() => {
                showNotification(`Reply form ready for ticket ${ticketId}!`, 'success');
            }, 1000);
        }

        function reopenTicket(ticketId) {
            if (confirm(`Are you sure you want to reopen ticket ${ticketId}?`)) {
                showNotification(`Reopening ticket ${ticketId}...`, 'info');
                setTimeout(() => {
                    showNotification(`Ticket ${ticketId} reopened successfully!`, 'success');
                }, 1000);
            }
        }

        function saveDraft() {
            showNotification('Saving draft...', 'info');
            // This would typically save the current form as a draft
            setTimeout(() => {
                showNotification('Draft saved successfully!', 'success');
            }, 1000);
        }

        function viewKnowledgeBase() {
            showNotification('Opening knowledge base...', 'info');
            // This would typically open the knowledge base
            setTimeout(() => {
                showNotification('Knowledge base loaded!', 'success');
            }, 1000);
        }

        function scheduleCall() {
            showNotification('Opening call scheduler...', 'info');
            // This would typically open a calendar/scheduler interface
            setTimeout(() => {
                showNotification('Call scheduler ready!', 'success');
            }, 1000);
        }
   