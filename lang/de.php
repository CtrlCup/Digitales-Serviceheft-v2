<?php
// Deutsch
// German
return [
    'app_name' => 'Digitales Serviceheft',
    'login_title' => 'Anmeldung',
    'register_title' => 'Registrierung',
    'dashboard_title' => 'Übersicht',
    'welcome' => 'Willkommen',
    'good_morning' => 'Guten Morgen',
    'good_day' => 'Willkommen',
    'good_evening' => 'Guten Abend',
    'dashboard_intro' => 'Hier wird später dein digitales Serviceheft erscheinen.',

    'email' => 'E-Mail',
    'username' => 'Benutzername',
    'identifier' => 'E-Mail oder Benutzername',
    'password' => 'Passwort',
    'password_confirm' => 'Passwort bestätigen',
    'name' => 'Name',

    'login_button' => 'Anmelden',
    'register_button' => 'Registrieren',
    'to_register' => 'Noch kein Konto? Jetzt registrieren',
    'to_login' => 'Schon ein Konto? Jetzt anmelden',
    'logout' => 'Abmelden',

    'login_failed' => 'Anmeldung fehlgeschlagen. Bitte E-Mail/Passwort prüfen.',
    'account_locked_wait' => 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte warte noch %s, bevor du es erneut versuchst.',
    'account_locked_remaining' => 'Dein Account ist gesperrt. Verbleibende Zeit: %s Tage, %s Stunden, %s Minuten, %s Sekunden.',
    'register_failed' => 'Registrierung fehlgeschlagen.',
    'password_mismatch' => 'Die Passwörter stimmen nicht überein.',
    'password_too_short' => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
    'password_weak' => 'Das Passwort muss mindestens einen Großbuchstaben, einen Kleinbuchstaben und eine Zahl enthalten.',
    'email_already_exists' => 'Diese E-Mail-Adresse ist bereits vergeben.',
    'username_already_exists' => 'Dieser Benutzername ist bereits vergeben.',
    'csrf_invalid' => 'Ungültiger Sicherheits-Token. Bitte erneut versuchen.',
    
    // Passwort-Validierung Live
    'pwd_req_title' => 'Passwort-Anforderungen:',
    'pwd_req_length' => 'Mindestens 8 Zeichen',
    'pwd_req_uppercase' => 'Mindestens ein Großbuchstabe',
    'pwd_req_lowercase' => 'Mindestens ein Kleinbuchstabe',
    'pwd_req_number' => 'Mindestens eine Zahl',
    'pwd_match' => 'Passwörter stimmen überein ✓',
    'pwd_no_match' => 'Passwörter stimmen nicht überein',

    'toggle_theme' => 'Theme wechseln',

    // Browser-Tab Titel
    'page_title_login' => 'Anmeldung',
    'page_title_register' => 'Registrierung',
    'page_title_dashboard' => 'Übersicht',
    'page_title_account' => 'Mein Konto',

    // Account / Profil
    'account_title' => 'Mein Konto',
    'profile_section' => 'Profil',
    'password_section' => 'Passwort ändern',
    'save_profile' => 'Profil speichern',
    'current_password' => 'Aktuelles Passwort',
    'new_password' => 'Neues Passwort',
    'new_password_confirm' => 'Neues Passwort bestätigen',
    'save_password' => 'Passwort speichern',
    'profile_saved' => 'Profil erfolgreich aktualisiert.',
    'password_saved' => 'Passwort erfolgreich geändert.',
    'account_link' => 'Konto',
    'invalid_email' => 'Ungültige E-Mail-Adresse.',
    'invalid_username' => 'Ungültiger Benutzername. Nur Buchstaben, Zahlen, Punkt, Unterstrich und Bindestrich sind erlaubt (3-32 Zeichen).',
    'invalid_name' => 'Name muss mindestens 2 Zeichen lang sein.',
    
    // Sprache / Language Settings
    'language_settings_title' => 'Sprache',
    'language_settings_description' => 'Lege die Anzeigesprache der Benutzeroberfläche fest.',
    'language_label' => 'Sprache auswählen',
    'language_save_button' => 'Sprache speichern',
    'language_saved_success' => 'Sprache wurde gespeichert. Die Seite zeigt nun die gewählte Sprache an.',
    'language_de' => 'Deutsch',
    'language_en' => 'Englisch',
    
    // Availability Check
    'availability_error' => 'Bitte korrigiere die markierten Felder, bevor du speicherst.',
    'availability_error_register' => 'Bitte wähle einen verfügbaren Benutzernamen und eine verfügbare E-Mail-Adresse.',
    'username_available' => 'Benutzername verfügbar',
    'username_taken' => 'Benutzername bereits vergeben',
    'email_available' => 'E-Mail verfügbar',
    'email_taken' => 'E-Mail bereits vergeben',
    'email_format_invalid' => 'Ungültiges E-Mail-Format (z.B. name@domain.de)',
    'checking_availability' => 'Überprüfe Verfügbarkeit...',
    
    // Sicherheit / Security
    'security_section' => 'Sicherheit',
    '2fa_title' => 'Zwei-Faktor-Authentifizierung (2FA)',
    '2fa_description' => 'Schütze dein Konto mit einer zusätzlichen Sicherheitsebene.',
    '2fa_status_enabled' => 'Aktiviert',
    '2fa_status_disabled' => 'Deaktiviert',
    '2fa_enable_button' => '2FA aktivieren',
    '2fa_disable_button' => '2FA deaktivieren',
    '2fa_setup_title' => '2FA einrichten',
    '2fa_setup_step1' => 'Scanne diesen QR-Code mit deiner Authenticator-App (z.B. Google Authenticator, Authy)',
    '2fa_setup_step2' => 'Oder gib diesen Code manuell ein:',
    '2fa_setup_step3' => 'Gib den 6-stelligen Code aus deiner App ein, um die Einrichtung abzuschließen:',
    '2fa_verify_code' => 'Verifizierungscode',
    '2fa_verify_button' => '2FA aktivieren',
    '2fa_recovery_codes_title' => 'Notfall-Wiederherstellungscodes',
    '2fa_recovery_codes_description' => 'Speichere diese Codes sicher. Du kannst sie verwenden, wenn du keinen Zugriff auf deine Authenticator-App hast.',
    '2fa_recovery_codes_warning' => 'Jeder Code kann nur einmal verwendet werden.',
    '2fa_enabled_success' => '2FA wurde erfolgreich aktiviert.',
    '2fa_disabled_success' => '2FA wurde deaktiviert.',
    '2fa_invalid_code' => 'Ungültiger Verifizierungscode.',
    '2fa_code_required' => 'Bitte gib den 6-stelligen Code aus deiner Authenticator-App ein.',
    '2fa_code_label' => '2FA Code',
    '2fa_or_recovery_code' => 'Alternativ kannst du auch einen Notfall-Wiederherstellungscode eingeben (Format: 1234-5678).',
    
    'passkeys_title' => 'Passkeys',
    'passkeys_description' => 'Melde dich mit biometrischen Daten oder deinem Gerät an.',
    'passkeys_none' => 'Keine Passkeys registriert',
    'passkey_add_button' => 'Passkey hinzufügen',
    'passkey_remove_button' => 'Entfernen',
    'passkey_name_label' => 'Name des Passkeys',
    'passkey_name_placeholder' => 'z.B. "Mein iPhone" oder "Fingerabdruck"',
    'passkey_added_success' => 'Passkey wurde erfolgreich hinzugefügt.',
    'passkey_removed_success' => 'Passkey wurde entfernt.',
    'passkey_last_used' => 'Zuletzt verwendet:',
    'passkey_never_used' => 'Noch nie verwendet',
    
    'login_with_passkey' => 'Mit Passkey anmelden',
    'or_divider' => 'oder',
    
    // Account Deletion
    'delete_account_title' => 'Account löschen',
    'delete_account_description' => 'Diese Aktion kann nicht rückgängig gemacht werden. Alle deine Daten werden permanent gelöscht.',
    'delete_account_button' => 'Account löschen',
    'delete_account_confirm_title' => 'Bist du dir sicher?',
    'delete_account_confirm_text' => 'Diese Aktion löscht deinen Account unwiderruflich. Alle deine Daten, Einstellungen und Passkeys werden permanent gelöscht.',
    'delete_account_confirm_instruction' => 'Gib deine E-Mail-Adresse zur Bestätigung ein:',
    'delete_account_email_label' => 'Deine E-Mail-Adresse',
    'delete_account_confirm_button' => 'Account endgültig löschen',
    'delete_account_final_confirm' => 'Letzte Warnung: Dies löscht deinen Account unwiderruflich. Fortfahren?',
    'delete_account_email_mismatch' => 'Die eingegebene E-Mail-Adresse stimmt nicht überein.',
    'cancel' => 'Abbrechen',
    
    // Passkey-Registrierung
    'passkey_enter_name' => 'Bitte gib einen Namen für den Passkey ein.',
    'passkey_registering' => 'Passkey wird registriert...',
    'passkey_use_authenticator' => 'Bitte verwende deinen Authenticator...',
    'passkey_error' => 'Fehler',
    
    // Fehler-Nachrichten (Backend)
    'error_invalid_email' => 'Ungültige E-Mail-Adresse',
    'error_invalid_username' => 'Ungültiger Benutzername',
    'error_database' => 'Datenbankfehler',
    'error_current_password_invalid' => 'Aktuelles Passwort ist ungültig',
    'error_no_setup_session' => 'Keine Setup-Sitzung gefunden. Bitte beginne von vorne.',
    
    // Admin & User Management
    'admin_panel_title' => 'Verwaltung',
    'admin_panel_description' => 'Verwalte Einstellungen und Benutzer.',
    'admin_settings_title' => 'Einstellungen',
    'admin_settings_link' => 'Einstellungen',
    'user_management_title' => 'Benutzerverwaltung',
    'user_management_description' => 'Verwalte alle registrierten Benutzer und deren Berechtigungen.',
    'user_management_intro' => 'Hier kannst du die Rollen aller Benutzer verwalten. Die Hierarchie ist: Betrachter < User < Admin < Owner.',
    'manage_users' => 'Benutzer verwalten',
    
    // Registration Settings
    'registration_settings_title' => 'Registrierung',
    'registration_settings_description' => 'Steuere, ob sich neue Benutzer registrieren können.',
    'registration_status' => 'Registrierungsstatus',
    'registration_enabled' => 'Aktiviert',
    'registration_disabled' => 'Deaktiviert',
    'enable_registration' => 'Registrierung aktivieren',
    'disable_registration' => 'Registrierung deaktivieren',
    'registration_enabled_success' => 'Registrierung wurde aktiviert.',
    'registration_disabled_success' => 'Registrierung wurde deaktiviert.',
    
    // Roles
    'role' => 'Rolle',
    'role_viewer' => 'Betrachter',
    'role_user' => 'User',
    'role_admin' => 'Admin',
    'role_owner' => 'Owner',
    'change_role' => 'Rolle ändern...',
    'role_changed_success' => 'Rolle wurde erfolgreich geändert.',
    
    // Role Descriptions
    'role_hierarchy_title' => 'Rollen-Hierarchie:',
    'role_owner_description' => 'Hat volle Kontrolle über alle Benutzer und Einstellungen. Kann nur einmal vergeben werden.',
    'role_admin_description' => 'Kann User zu Admin befördern, aber nicht zurückstufen.',
    'role_user_description' => 'Kann Betrachter zu User befördern.',
    'role_viewer_description' => 'Kann nur Inhalte ansehen, aber keine Änderungen vornehmen.',
    
    // Errors
    'error_user_not_found' => 'Benutzer nicht gefunden.',
    'error_invalid_role' => 'Ungültige Rolle.',
    'error_permission_denied' => 'Keine Berechtigung für diese Aktion.',
    'error_cannot_demote' => 'Du kannst Benutzer nicht zurückstufen.',
    'error_owner_exists' => 'Es kann nur einen Owner geben.',
    
    // General
    'actions' => 'Aktionen',
    'registered_at' => 'Registriert am',
    'status' => 'Status',
    'locked' => 'Gesperrt',
    'active' => 'Aktiv',
    'you' => 'Du',
    
    // Admin actions
    'change_email' => 'Email ändern',
    'change_password' => 'Passwort ändern',
    'delete_account' => 'Account löschen',
    'lock_account' => 'Account sperren',
    'unlock_account' => 'Account entsperren',
    'new_email' => 'Neue Email',
    'new_password' => 'Neues Passwort',
    'delete_account_confirm' => 'Möchten Sie den Account von',
    'action_irreversible' => 'Diese Aktion kann nicht rückgängig gemacht werden.',
    
    // Success messages
    'email_changed_success' => 'Email erfolgreich geändert.',
    'password_changed_success' => 'Passwort erfolgreich geändert.',
    'account_locked_success' => 'Account erfolgreich gesperrt.',
    'account_unlocked_success' => 'Account erfolgreich entsperrt.',
    'account_deleted_success' => 'Account erfolgreich gelöscht.',
    
    // Errors
    'error_cannot_lock_owner' => 'Owner-Accounts können nicht gesperrt werden.',
    'error_cannot_lock_self' => 'Sie können Ihren eigenen Account nicht sperren.',
    'error_cannot_modify_owner' => 'Owner-Accounts können nicht modifiziert werden.',
    'error_cannot_delete_owner' => 'Owner-Accounts können nicht gelöscht werden.',
    'error_cannot_delete_self' => 'Sie können Ihren eigenen Account nicht löschen.',
    
    // Admin > Test-E-Mail
    'test_email_card_title' => 'Test-E-Mail senden',
    'test_email_card_description' => 'Sende eine Test-E-Mail über die konfigurierte SMTP-Verbindung.',
    'test_email_recipient' => 'Empfänger',
    'test_email_subject' => 'Betreff',
    'test_email_message' => 'Nachricht',
    'test_email_button' => 'Testmail senden',
    'test_email_sent_success' => 'Test-E-Mail wurde gesendet an',
    'test_email_send_failed' => 'Senden der Test-E-Mail ist fehlgeschlagen. Bitte SMTP-Konfiguration prüfen.',
    'test_email_hint' => '',
    'test_email_subject_prefix' => 'Test-E-Mail von',
    'test_email_body_line1' => 'Hallo,',
    'test_email_body_line2' => 'Dies ist eine Test-E-Mail von',
    'test_email_body_line3' => 'Falls diese Mail ankommt, ist die SMTP-Konfiguration korrekt.',
    
    // Account confirmations & passkeys
    'confirm_disable_2fa' => 'Möchtest du 2FA wirklich deaktivieren? Dies verringert die Sicherheit deines Kontos.',
    'confirm_remove_passkey' => 'Möchtest du diesen Passkey wirklich entfernen?',
    'passkey_default_prefix' => 'Passkey #',
    
    // Login & 2FA
    'session_expired_login_again' => 'Sitzung abgelaufen. Bitte neu anmelden.',
    '2fa_placeholder' => '000 000',
    
    // Passkey auth (JS/ui)
    'connecting' => 'Verbinde...'
    ,'passkey_login_failed' => 'Passkey-Login fehlgeschlagen'
    ,'passkey_get_options_failed' => 'Abrufen der Optionen fehlgeschlagen'
    ,'passkey_no_credential' => 'Kein Anmeldedatensatz ausgewählt'
    ,'passkey_auth_failed_generic' => 'Authentifizierung fehlgeschlagen'
    ,'close' => 'Schließen'
    ,'lock_until' => 'Account sperren bis:'
    ,'date' => 'Datum'
    ,'time' => 'Uhrzeit'
    ,'save' => 'Speichern',

    // Registrierung: E-Mail-Verifizierung
    'verify_email_page_title' => 'E-Mail bestätigen',
    'verify_email_intro' => 'Wir haben dir eine E-Mail mit einem Bestätigungscode und einem Link gesendet.',
    'verify_email_enter_code' => 'Bestätigungscode eingeben',
    'verify_email_submit' => 'E-Mail bestätigen',
    'verify_email_subject_prefix' => 'E-Mail bestätigen für',
    'verify_email_hello' => 'Hallo!',
    'verify_email_body_intro' => 'Bitte bestätige deine E-Mail-Adresse für',
    'verify_email_code_label' => 'Dein Bestätigungscode',
    'verify_email_link_text' => 'Hier klicken, um zu bestätigen',
    'verify_email_thanks' => 'Vielen Dank!',
    'verification_success' => 'Deine E-Mail-Adresse wurde erfolgreich bestätigt.',
    'verification_failed' => 'Ungültiger oder abgelaufener Code. Bitte erneut versuchen.',

    // E-Mail-Änderung per Link
    'email_change_subject_prefix' => 'E-Mail-Adresse bestätigen',
    'email_change_body_intro' => 'Bitte bestätige die Änderung deiner E-Mail-Adresse.',
    'email_change_link_text' => 'E-Mail-Änderung bestätigen',
    'email_change_requested' => 'Wir haben dir eine E-Mail mit einem Link gesendet, um die neue E-Mail-Adresse zu bestätigen.',
    'email_change_success' => 'Deine E-Mail-Adresse wurde erfolgreich geändert.',
    'email_change_invalid' => 'Der Bestätigungslink ist ungültig oder abgelaufen.',

    'vehicles_details_title' => 'Fahrzeugdetails',
    'vehicles_back_to_overview' => 'Zur Übersicht',
    'vehicles_back_to_detail' => 'Zurück zur Detailansicht',
    'vehicles_overview_title' => 'Fahrzeugübersicht',
    'vehicles_create_cta' => 'Neues Auto anlegen',
    'your_vehicles' => 'Deine Fahrzeuge',
    'no_vehicles_yet' => 'Noch keine Fahrzeuge vorhanden.',
    'vehicle_create_title' => 'Neues Fahrzeug anlegen',
    'vehicles_load_failed' => 'Fahrzeuge konnten nicht geladen werden.',
    'error_title' => 'Fehler',
    'vehicle_not_found' => 'Fahrzeug wurde nicht gefunden.',
    'vehicle_load_failed' => 'Fahrzeug konnte nicht geladen werden.',
    'edit' => 'Bearbeiten',
    'add_service' => 'Service hinzufügen',
    'remove' => 'Entfernen',
    'vehicle_edit_title' => 'Fahrzeug bearbeiten',
    'vehicle_data' => 'Fahrzeugdaten',
    'hsn' => 'HSN',
    'tsn' => 'TSN',
    'vin' => 'FIN (VIN)',
    'make' => 'Hersteller',
    'model' => 'Modell',
    'first_registration' => 'Erstzulassung',
    'first_registration_with_format' => 'Erstzulassung (TT.MM.JJJJ)',
    'example_date_de' => 'z.B. 23.06.1999',
    'license_plate' => 'Kennzeichen',
    'color' => 'Farbe',
    'engine_code' => 'Motorcode',
    'fuel_type' => 'Kraftstofftyp',
    'odometer_km' => 'Kilometerstand',
    'purchase_date' => 'Kaufdatum',
    'purchase_price' => 'Kaufpreis',
    'vehicle_image_alt' => 'Fahrzeugbild',
    'notes' => 'Notizen'
    ,'technical_data' => 'Technische Daten'
    ,'purchase_details' => 'Kaufdetails'
    ,'additional_notes' => 'Zusätzliche Notizen'
    ,'profile_image_hint' => 'Profilbild (JPG/PNG/WEBP, max. 5 MB) — leer lassen, um nicht zu ändern'
    ,'profile_image_hint_create' => 'Profilbild (JPG/PNG/WEBP, max. 5 MB)'
    ,'current_image_alt' => 'Aktuelles Bild'
    ,'delete_current_image' => 'Aktuelles Bild löschen'
    ,'delete_image' => 'Bild löschen'
    ,'example_odometer' => 'z.B. 125000'
    ,'fuel_petrol' => 'Benzin'
    ,'fuel_diesel' => 'Diesel'
    ,'fuel_electric' => 'Elektrisch'
    ,'fuel_hybrid' => 'Hybrid'
    ,'fuel_lpg' => 'LPG'
    ,'fuel_cng' => 'CNG'
    ,'fuel_hydrogen' => 'Wasserstoff'
    ,'fuel_other' => 'Sonstiges'
    ,'example_price' => 'z.B. 12.500,00'
    ,'unsaved_changes_leave_prompt' => 'Änderungen wurden noch nicht gespeichert. Seite wirklich verlassen?'
    ,'confirm_delete_current_image' => 'Aktuelles Bild wirklich löschen?'
    ,'image_will_be_deleted_on_save' => 'Wird beim Speichern gelöscht'
    ,'confirm_title' => 'Bist du dir sicher?'
    ,'vehicle_delete_warning' => 'Diese Aktion löscht dieses Fahrzeug unwiderruflich. Bilddatei und alle zugehörigen Einträge werden entfernt.'
    ,'vehicle_delete_instruction_prefix' => 'Gib zur Bestätigung das Kennzeichen'
    ,'vehicle_delete_instruction_suffix' => 'ein:'
    ,'vehicle_delete_confirm_button' => 'Fahrzeug endgültig löschen'
    ,'vehicle_save_failed' => 'Fahrzeug konnte nicht gespeichert werden.'
    ,'vehicle_update_failed' => 'Fahrzeug konnte nicht aktualisiert werden.'
    ,'vehicle_delete_failed' => 'Fahrzeug konnte nicht gelöscht werden.'
    ,'vehicle_not_found_or_forbidden' => 'Fahrzeug wurde nicht gefunden oder Zugriff verweigert.'
    ,'technical_error_prefix' => 'Technischer Fehler:'
    ,'dashboard_vehicles_load_failed' => 'Fahrzeugdaten konnten nicht geladen werden.'
    ,'notice_title' => 'Hinweis'
    ,'create_first_vehicle_hint' => 'Lege jetzt dein erstes Auto an, um Wartungen und Einträge zu verwalten.'
    ,'vehicle' => 'Fahrzeug'
    ,'password_reset_sent' => 'Neues Passwort wurde per E-Mail gesendet. Bitte Posteingang prüfen.'
    ,'reset_password' => 'Passwort zurücksetzen'
    ,'reset_password_info' => 'Du erhältst ein neues Passwort per E-Mail.'
    ,'dashboard_intro' => 'Hier wird später dein digitales Serviceheft erscheinen.'
    ,'no_vehicles_yet' => 'Aktuell hast du kein Fahrzeug angelegt'
    ,'your_vehicles' => 'Deine Fahrzeuge'
    ,'vehicle_image_alt' => 'Fahrzeugbild'
    ,'account_title' => 'Konto'
    ,'email_send_failed' => 'E-Mail konnte nicht gesendet werden.'
    ,'no_vehicles_yet' => 'Aktuell hast du kein Fahrzeug angelegt'
    ,'your_vehicles' => 'Deine Fahrzeuge'
    ,'vehicle_image_alt' => 'Fahrzeugbild'
    ,'account_title' => 'Konto'
    ,'email_send_failed' => 'E-Mail konnte nicht gesendet werden.'
];
