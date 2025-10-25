/**
 * Live Registration Field Validation (Username & Email)
 * Same as account-validator.js but for registration page
 */
(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    const usernameField = document.querySelector('input[name="username"]');
    const emailField = document.querySelector('input[name="email"]');
    
    // Only run on registration page (no existing values)
    if (!usernameField || !emailField || usernameField.value || emailField.value) return;
    
    const registerForm = usernameField.closest('form');
    if (!registerForm) return;
    
    const submitButton = registerForm.querySelector('button[type="submit"]');
    if (!submitButton) return;

    // Create validation feedback boxes
    const usernameBox = createFeedbackBox();
    const emailBox = createFeedbackBox();
    
    usernameField.parentElement.appendChild(usernameBox);
    emailField.parentElement.appendChild(emailBox);

    // Debounce timer
    let usernameTimeout = null;
    let emailTimeout = null;

    // Validation state
    let usernameValid = false;
    let emailValid = false;
    let emailFormatValid = false;
    
    // Function to update submit button state
    function updateSubmitButton() {
      if (usernameValid && emailValid && emailFormatValid) {
        submitButton.disabled = false;
        submitButton.style.opacity = '1';
        submitButton.style.cursor = 'pointer';
      } else {
        submitButton.disabled = true;
        submitButton.style.opacity = '0.5';
        submitButton.style.cursor = 'not-allowed';
      }
    }
    
    // Initially disable submit button
    updateSubmitButton();

    // Username validation
    usernameField.addEventListener('input', function() {
      clearTimeout(usernameTimeout);
      const value = this.value.trim();
      
      // Basic validation
      if (value.length < 3) {
        usernameBox.classList.remove('show');
        usernameValid = false;
        updateSubmitButton();
        return;
      }

      // Show loading state
      usernameBox.innerHTML = '<span class="feedback-icon">⏳</span><span class="feedback-text">' + 
        (window.__i18n?.checking_availability || 'Überprüfe Verfügbarkeit...') + '</span>';
      usernameBox.classList.add('show', 'checking');
      usernameBox.classList.remove('available', 'taken');

      // Debounce API call
      usernameTimeout = setTimeout(() => {
        checkAvailabilityPublic('username', value, usernameBox, (available) => {
          usernameValid = available;
          updateSubmitButton();
        });
      }, 500);
    });
    
    // Hide username box on blur
    usernameField.addEventListener('blur', function() {
      setTimeout(() => {
        usernameBox.classList.remove('show');
      }, 200);
    });
    
    // Show username box on focus if there's feedback
    usernameField.addEventListener('focus', function() {
      if (usernameBox.innerHTML && this.value.trim().length >= 3) {
        usernameBox.classList.add('show');
      }
    });

    // Email validation
    emailField.addEventListener('input', function() {
      clearTimeout(emailTimeout);
      const value = this.value.trim();
      
      // Email format validation (text@domain.tld)
      // Must have: local part + @ + domain + . + TLD (min 2 chars)
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
      emailFormatValid = emailRegex.test(value);
      
      // Additional check: ensure there's actual content after the dot
      if (emailFormatValid) {
        const parts = value.split('@');
        if (parts.length === 2) {
          const domainPart = parts[1];
          const domainParts = domainPart.split('.');
          // Must have domain AND tld (both non-empty)
          if (domainParts.length < 2 || domainParts[0].length < 1 || domainParts[domainParts.length - 1].length < 2) {
            emailFormatValid = false;
          }
        }
      }
      
      // Basic validation - check length first
      if (value.length < 5) {
        emailBox.classList.remove('show');
        emailValid = false;
        emailFormatValid = false;
        updateSubmitButton();
        return;
      }
      
      // Check email format
      if (!emailFormatValid) {
        emailBox.classList.add('show', 'taken');
        emailBox.classList.remove('available', 'checking');
        emailBox.innerHTML = '<span class="feedback-icon">✗</span><span class="feedback-text">' + 
          (window.__i18n?.email_format_invalid || 'Ungültiges E-Mail-Format (z.B. name@domain.de)') + '</span>';
        emailValid = false;
        updateSubmitButton();
        return;
      }

      // Show loading state for availability check
      emailBox.innerHTML = '<span class="feedback-icon">⏳</span><span class="feedback-text">' + 
        (window.__i18n?.checking_availability || 'Überprüfe Verfügbarkeit...') + '</span>';
      emailBox.classList.add('show', 'checking');
      emailBox.classList.remove('available', 'taken');

      // Debounce API call
      emailTimeout = setTimeout(() => {
        checkAvailabilityPublic('email', value, emailBox, (available) => {
          emailValid = available;
          updateSubmitButton();
        });
      }, 500);
    });
    
    // Hide email box on blur
    emailField.addEventListener('blur', function() {
      setTimeout(() => {
        emailBox.classList.remove('show');
      }, 200);
    });
    
    // Show email box on focus if there's feedback or format is invalid
    emailField.addEventListener('focus', function() {
      const value = this.value.trim();
      if (emailBox.innerHTML && value.length >= 3) {
        emailBox.classList.add('show');
      }
    });

    // Prevent form submission if validation fails (no alert needed, button is disabled)
    registerForm.addEventListener('submit', function(e) {
      if (!usernameValid || !emailValid || !emailFormatValid) {
        e.preventDefault();
      }
    });
  });

  function createFeedbackBox() {
    const box = document.createElement('div');
    box.className = 'availability-feedback';
    return box;
  }

  async function checkAvailabilityPublic(type, value, box, callback) {
    try {
      // For registration, we check without user context
      const response = await fetch(`/api/check-availability-public.php?type=${encodeURIComponent(type)}&value=${encodeURIComponent(value)}`);
      
      if (!response.ok) {
        throw new Error('Network error');
      }

      const data = await response.json();
      
      box.classList.remove('checking');
      
      if (data.available) {
        box.classList.add('available');
        box.classList.remove('taken');
        const text = type === 'username' 
          ? (window.__i18n?.username_available || 'Benutzername verfügbar')
          : (window.__i18n?.email_available || 'E-Mail verfügbar');
        box.innerHTML = '<span class="feedback-icon">✓</span><span class="feedback-text">' + text + '</span>';
        callback(true);
      } else {
        box.classList.add('taken');
        box.classList.remove('available');
        const text = type === 'username'
          ? (window.__i18n?.username_taken || 'Benutzername bereits vergeben')
          : (window.__i18n?.email_taken || 'E-Mail bereits vergeben');
        box.innerHTML = '<span class="feedback-icon">✗</span><span class="feedback-text">' + text + '</span>';
        callback(false);
      }
      
    } catch (error) {
      console.error('Availability check failed:', error);
      box.classList.remove('show', 'checking');
      callback(false);
    }
  }
})();
