/**
 * Live Account Field Validation (Username & Email)
 */
(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    const usernameField = document.querySelector('input[name="username"]');
    const emailField = document.querySelector('input[name="email"]');
    
    if (!usernameField || !emailField) return;
    
    // Find the profile form (contains the username field)
    const profileForm = usernameField.closest('form');
    if (!profileForm) return;

    // Store original values to skip checking if unchanged
    const originalUsername = usernameField.value;
    const originalEmail = emailField.value;

    // Create validation feedback boxes
    const usernameBox = createFeedbackBox();
    const emailBox = createFeedbackBox();
    
    usernameField.parentElement.appendChild(usernameBox);
    emailField.parentElement.appendChild(emailBox);

    // Debounce timer
    let usernameTimeout = null;
    let emailTimeout = null;

    // Validation state
    let usernameValid = true;
    let emailValid = true;

    // Username validation
    usernameField.addEventListener('input', function() {
      clearTimeout(usernameTimeout);
      const value = this.value.trim();
      
      // If same as original, it's valid
      if (value === originalUsername) {
        usernameBox.classList.remove('show', 'available', 'taken');
        usernameValid = true;
        return;
      }

      // Basic validation
      if (value.length < 3) {
        usernameBox.classList.remove('show');
        usernameValid = false;
        return;
      }

      // Show loading state
      usernameBox.innerHTML = '<span class="feedback-icon">⏳</span><span class="feedback-text">' + 
        (window.__i18n?.checking_availability || 'Überprüfe Verfügbarkeit...') + '</span>';
      usernameBox.classList.add('show', 'checking');
      usernameBox.classList.remove('available', 'taken');

      // Debounce API call
      usernameTimeout = setTimeout(() => {
        checkAvailability('username', value, usernameBox, (available) => {
          usernameValid = available;
        });
      }, 500);
    });

    // Email validation
    emailField.addEventListener('input', function() {
      clearTimeout(emailTimeout);
      const value = this.value.trim();
      
      // If same as original, it's valid
      if (value === originalEmail) {
        emailBox.classList.remove('show', 'available', 'taken');
        emailValid = true;
        return;
      }

      // Basic validation
      if (!value.includes('@') || value.length < 5) {
        emailBox.classList.remove('show');
        emailValid = false;
        return;
      }

      // Show loading state
      emailBox.innerHTML = '<span class="feedback-icon">⏳</span><span class="feedback-text">' + 
        (window.__i18n?.checking_availability || 'Überprüfe Verfügbarkeit...') + '</span>';
      emailBox.classList.add('show', 'checking');
      emailBox.classList.remove('available', 'taken');

      // Debounce API call
      emailTimeout = setTimeout(() => {
        checkAvailability('email', value, emailBox, (available) => {
          emailValid = available;
        });
      }, 500);
    });

    // Prevent form submission if validation fails
    profileForm.addEventListener('submit', function(e) {
      if (!usernameValid || !emailValid) {
        e.preventDefault();
        alert(window.__i18n?.availability_error || 'Bitte korrigiere die markierten Felder, bevor du speicherst.');
      }
    });
  });

  function createFeedbackBox() {
    const box = document.createElement('div');
    box.className = 'availability-feedback';
    return box;
  }

  async function checkAvailability(type, value, box, callback) {
    try {
      const response = await fetch(`/api/check-availability.php?type=${encodeURIComponent(type)}&value=${encodeURIComponent(value)}`);
      
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
