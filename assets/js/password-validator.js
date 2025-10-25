/**
 * Live Password Validation for Registration Form
 */
(function() {
  'use strict';

  // Warte bis DOM geladen ist
  document.addEventListener('DOMContentLoaded', function() {
    // Try to find password fields (works for both register and account pages)
    let passwordField = document.querySelector('input[name="password"]');
    let confirmField = document.querySelector('input[name="password_confirm"]');
    
    // If not found, try new_password fields (for account password change)
    if (!passwordField) {
      passwordField = document.querySelector('input[name="new_password"]');
      confirmField = document.querySelector('input[name="new_password_confirm"]');
    }
    
    if (!passwordField || !confirmField) return;

    // Erstelle Validierungs-Anzeigen
    const validationBox = createValidationBox();
    const confirmBox = createConfirmBox();
    
    passwordField.parentElement.appendChild(validationBox);
    confirmField.parentElement.appendChild(confirmBox);

    // Event Listeners
    passwordField.addEventListener('focus', () => {
      validationBox.classList.add('show');
    });

    passwordField.addEventListener('blur', () => {
      setTimeout(() => validationBox.classList.remove('show'), 200);
    });

    passwordField.addEventListener('input', () => {
      validatePassword(passwordField.value, validationBox);
      if (confirmField.value) {
        validateConfirm(passwordField.value, confirmField.value, confirmBox);
      }
    });

    confirmField.addEventListener('focus', () => {
      if (confirmField.value || passwordField.value) {
        confirmBox.classList.add('show');
      }
    });

    confirmField.addEventListener('blur', () => {
      setTimeout(() => confirmBox.classList.remove('show'), 200);
    });

    confirmField.addEventListener('input', () => {
      validateConfirm(passwordField.value, confirmField.value, confirmBox);
      if (confirmField.value) {
        confirmBox.classList.add('show');
      }
    });
  });

  function createValidationBox() {
    const box = document.createElement('div');
    box.className = 'pwd-validation-box';
    box.innerHTML = `
      <div class="pwd-validation-title">${window.__i18n?.pwd_req_title || 'Passwort-Anforderungen:'}</div>
      <div class="pwd-requirement" data-check="length">
        <span class="pwd-icon">✗</span>
        <span class="pwd-text">${window.__i18n?.pwd_req_length || 'Mindestens 8 Zeichen'}</span>
      </div>
      <div class="pwd-requirement" data-check="uppercase">
        <span class="pwd-icon">✗</span>
        <span class="pwd-text">${window.__i18n?.pwd_req_uppercase || 'Mindestens ein Großbuchstabe'}</span>
      </div>
      <div class="pwd-requirement" data-check="lowercase">
        <span class="pwd-icon">✗</span>
        <span class="pwd-text">${window.__i18n?.pwd_req_lowercase || 'Mindestens ein Kleinbuchstabe'}</span>
      </div>
      <div class="pwd-requirement" data-check="number">
        <span class="pwd-icon">✗</span>
        <span class="pwd-text">${window.__i18n?.pwd_req_number || 'Mindestens eine Zahl'}</span>
      </div>
    `;
    return box;
  }

  function createConfirmBox() {
    const box = document.createElement('div');
    box.className = 'pwd-confirm-box';
    box.innerHTML = `
      <span class="pwd-icon">✗</span>
      <span class="pwd-text">${window.__i18n?.pwd_no_match || 'Passwörter stimmen nicht überein'}</span>
    `;
    return box;
  }

  function validatePassword(password, box) {
    const checks = {
      length: password.length >= 8,
      uppercase: /[A-Z]/.test(password),
      lowercase: /[a-z]/.test(password),
      number: /[0-9]/.test(password)
    };

    // Update jede Anforderung
    Object.keys(checks).forEach(check => {
      const requirement = box.querySelector(`[data-check="${check}"]`);
      const icon = requirement.querySelector('.pwd-icon');
      
      if (checks[check]) {
        requirement.classList.add('valid');
        icon.textContent = '✓';
      } else {
        requirement.classList.remove('valid');
        icon.textContent = '✗';
      }
    });

    // Wenn alle erfüllt, Box grün machen
    const allValid = Object.values(checks).every(v => v);
    if (allValid) {
      box.classList.add('all-valid');
    } else {
      box.classList.remove('all-valid');
    }
  }

  function validateConfirm(password, confirm, box) {
    const matches = password === confirm && confirm.length > 0;
    const icon = box.querySelector('.pwd-icon');
    const text = box.querySelector('.pwd-text');

    if (matches) {
      box.classList.add('valid');
      box.classList.remove('invalid');
      icon.textContent = '✓';
      text.textContent = window.__i18n?.pwd_match || 'Passwörter stimmen überein ✓';
    } else {
      box.classList.remove('valid');
      box.classList.add('invalid');
      icon.textContent = '✗';
      text.textContent = window.__i18n?.pwd_no_match || 'Passwörter stimmen nicht überein';
    }
  }
})();
