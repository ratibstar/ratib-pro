/**
 * EN: Implements frontend interaction behavior in `js/agent/add-agent.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/agent/add-agent.js`.
 */
/**
 * Add Agent Form Handler
 * Handles form submission via API
 */

document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.add-form');
    if (!form) return;
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Adding...';
        
        // Clear previous messages
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());
        
        try {
            const formData = new FormData(form);
            const data = {
                username: formData.get('username'),
                password: formData.get('password'),
                email: formData.get('email'),
                full_name: formData.get('full_name'),
                phone: formData.get('phone'),
                address: formData.get('address'),
                commission_rate: parseFloat(formData.get('commission_rate'))
            };
            
            const apiBase = (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '');
            const response = await fetch(`${apiBase}/agents/create.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                const successDiv = document.createElement('div');
                successDiv.className = 'alert alert-success';
                successDiv.textContent = result.message || 'Agent added successfully!';
                form.parentElement.insertBefore(successDiv, form);
                
                // Redirect after 2 seconds
                setTimeout(() => {
                    window.location.href = 'agent.php';
                }, 2000);
            } else {
                // Show error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-error';
                errorDiv.textContent = result.message || 'Error adding agent';
                form.parentElement.insertBefore(errorDiv, form);
                
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        } catch (error) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error';
            errorDiv.textContent = 'An error occurred. Please try again.';
            form.parentElement.insertBefore(errorDiv, form);
            
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
    
    // Handle back button
    const backBtn = document.querySelector('.back-btn');
    if (backBtn) {
        backBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'agent.php';
        });
    }
    
    // Handle cancel button
    const cancelBtn = document.querySelector('.btn-secondary');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'agent.php';
        });
    }
});

