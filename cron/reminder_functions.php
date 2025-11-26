<?php
/**
 * Service Reminder Functions
 * Contains the core logic for sending service reminders
 * Can be included by both CLI script and web trigger
 */

// Function to send reminder email
function send_reminder_email(array $user, array $reminders): bool {
    if (empty($user['email'])) {
        return false;
    }
    
    $subject = t('reminder_email_subject');
    $appName = defined('APP_NAME') ? APP_NAME : 'Digitales Serviceheft';
    
    // Build HTML email
    $html = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
    $html .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
    $html .= '<h2 style="color: #2563eb;">' . e($appName) . '</h2>';
    $html .= '<p>' . e(t('reminder_email_greeting')) . ' ' . e($user['name']) . ',</p>';
    $html .= '<p>' . e(t('reminder_email_intro')) . '</p>';
    
    $html .= '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
    $html .= '<thead><tr style="background-color: #f3f4f6;">';
    $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">' . e(t('reminder_email_vehicle_label')) . '</th>';
    $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">' . e(t('reminder_email_service_type')) . '</th>';
    $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">' . e(t('reminder_email_due_date')) . '</th>';
    $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">' . e(t('reminder_email_due_km')) . '</th>';
    $html .= '</tr></thead><tbody>';
    
    foreach ($reminders as $reminder) {
        $html .= '<tr>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . e($reminder['vehicle_name']) . '</td>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . e($reminder['service_type']) . '</td>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . e($reminder['due_date'] ?? '-') . '</td>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;">';
        if ($reminder['due_km']) {
            $html .= e(number_format($reminder['due_km'], 0, ',', '.')) . ' km';
            if ($reminder['current_km']) {
                $html .= '<br><small style="color: #6b7280;">' . e(t('reminder_email_current_km')) . ': ' . e(number_format($reminder['current_km'], 0, ',', '.')) . ' km</small>';
            }
        } else {
            $html .= '-';
        }
        $html .= '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    $html .= '<p>' . e(t('reminder_email_footer')) . '</p>';
    
    // Add CTA button
    $domain = defined('APP_DOMAIN') ? APP_DOMAIN : ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $url = $protocol . $domain . '/vehicles/overview.php';
    
    $html .= '<div style="margin: 30px 0;">';
    $html .= '<a href="' . e($url) . '" style="display: inline-block; padding: 12px 24px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 6px;">' . e(t('reminder_email_cta')) . '</a>';
    $html .= '</div>';
    
    $html .= '<hr style="margin: 30px 0; border: none; border-top: 1px solid #e5e7eb;">';
    $html .= '<p style="color: #6b7280; font-size: 12px;">' . e(t('reminder_email_signature')) . '</p>';
    $html .= '</div></body></html>';
    
    // Plain text version
    $text = t('reminder_email_greeting') . ' ' . $user['name'] . ",\n\n";
    $text .= t('reminder_email_intro') . "\n\n";
    foreach ($reminders as $reminder) {
        $text .= "- " . $reminder['vehicle_name'] . ": " . $reminder['service_type'];
        if ($reminder['due_date']) {
            $text .= " (" . t('reminder_email_due_date') . ": " . $reminder['due_date'] . ")";
        }
        if ($reminder['due_km']) {
            $text .= " (" . t('reminder_email_due_km') . ": " . number_format($reminder['due_km'], 0, ',', '.') . " km)";
        }
        $text .= "\n";
    }
    $text .= "\n" . t('reminder_email_footer') . "\n\n";
    $text .= t('reminder_email_cta') . ": " . $url . "\n\n";
    $text .= t('reminder_email_signature');
    
    try {
        send_email($user['email'], $subject, $html, $text);
        return true;
    } catch (Throwable $e) {
        error_log("Failed to send reminder email to {$user['email']}: " . $e->getMessage());
        return false;
    }
}

// Main reminder logic
function run_service_reminders(): void {
    $pdo = db();
    $today = date('Y-m-d');
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting service reminder check...\n";
    
    // Get all users with reminders enabled
    $stmt = $pdo->prepare("
        SELECT id, name, email, reminder_days_advance, locale, timezone
        FROM users 
        WHERE reminder_enabled = 1 
        AND email IS NOT NULL 
        AND email_verified_at IS NOT NULL
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo "Found " . count($users) . " users with reminders enabled.\n";
    
    $emailsSent = 0;
    $servicesReminded = 0;
    $servicesSkipped = 0;
    
    foreach ($users as $user) {
        // Load user's locale for translations
        if (!empty($user['locale'])) {
            load_locale($user['locale']);
        }
        
        $daysAdvance = (int)($user['reminder_days_advance'] ?? 7);
        $checkDate = date('Y-m-d', strtotime("+{$daysAdvance} days"));
        
        // Find services due soon for this user's vehicles
        $stmt = $pdo->prepare("
            SELECT 
                se.id as entry_id,
                se.vehicle_id,
                se.type as service_type,
                se.next_due_date,
                se.next_due_km,
                v.make,
                v.model,
                v.license_plate,
                v.odometer_km as current_km
            FROM service_entries se
            JOIN vehicles v ON v.id = se.vehicle_id
            WHERE v.user_id = ?
            AND (
                (se.next_due_date IS NOT NULL AND se.next_due_date <= ?)
                OR (se.next_due_km IS NOT NULL AND v.odometer_km IS NOT NULL AND v.odometer_km >= (se.next_due_km - 1000))
            )
        ");
        $stmt->execute([(int)$user['id'], $checkDate]);
        $dueServices = $stmt->fetchAll();
        
        if (empty($dueServices)) {
            continue;
        }
        
        // Filter out services for which we already sent a reminder
        $remindersToSend = [];
        
        foreach ($dueServices as $service) {
            // Check if we already sent a reminder for this service
            $stmt = $pdo->prepare("
                SELECT id FROM service_reminders_sent
                WHERE service_entry_id = ? 
                AND user_id = ?
                AND sent_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([(int)$service['entry_id'], (int)$user['id']]);
            
            if ($stmt->fetch()) {
                $servicesSkipped++;
                continue; // Already sent recently
            }
            
            $vehicleName = $service['make'] . ' ' . $service['model'];
            if ($service['license_plate']) {
                $vehicleName .= ' (' . $service['license_plate'] . ')';
            }
            
            $remindersToSend[] = [
                'entry_id' => $service['entry_id'],
                'vehicle_name' => $vehicleName,
                'service_type' => $service['service_type'],
                'due_date' => $service['next_due_date'],
                'due_km' => $service['next_due_km'],
                'current_km' => $service['current_km']
            ];
        }
        
        if (empty($remindersToSend)) {
            continue;
        }
        
        // Send email
        if (send_reminder_email($user, $remindersToSend)) {
            // Log sent reminders
            foreach ($remindersToSend as $reminder) {
                $stmt = $pdo->prepare("
                    INSERT INTO service_reminders_sent 
                    (service_entry_id, user_id, reminder_type, due_date, due_km)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $reminderType = $reminder['due_date'] ? 'date' : 'km';
                $stmt->execute([
                    $reminder['entry_id'],
                    (int)$user['id'],
                    $reminderType,
                    $reminder['due_date'],
                    $reminder['due_km']
                ]);
            }
            
            $emailsSent++;
            $servicesReminded += count($remindersToSend);
            echo "✓ Sent reminder to {$user['email']} for " . count($remindersToSend) . " service(s)\n";
        } else {
            echo "✗ Failed to send reminder to {$user['email']}\n";
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Reminder check completed.\n";
    echo "Summary: {$emailsSent} email(s) sent with {$servicesReminded} service reminder(s), {$servicesSkipped} service(s) skipped (already reminded recently)\n";
}
