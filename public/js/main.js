// Mobile sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
    // Create mobile toggle button
    const topBar = document.querySelector('.top-bar');
    if (topBar) {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'mobile-toggle';
        toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        toggleBtn.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('open');
        });
        topBar.insertBefore(toggleBtn, topBar.firstChild);
    }
    
    // Close sidebar on outside click (mobile)
    document.addEventListener('click', function(event) {
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.querySelector('.mobile-toggle');
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                sidebar.classList.remove('open');
            }
        }
    });
    
    // Auto-hide alerts
    document.querySelectorAll('.alert').forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
});

// Form validation helper
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let valid = true;
    
    inputs.forEach(function(input) {
        if (!input.value.trim()) {
            input.style.borderColor = '#e74c3c';
            valid = false;
        } else {
            input.style.borderColor = '#dee2e6';
        }
    });
    
    return valid;
}

// Number formatting helper
function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

// Currency formatting helper
function formatCurrency(amount) {
    return 'UGX ' + new Intl.NumberFormat().format(amount);
}

// Date formatting helper
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-GB');
}

// Export functions for use in inline scripts
window.validateForm = validateForm;
window.formatNumber = formatNumber;
window.formatCurrency = formatCurrency;
window.formatDate = formatDate;