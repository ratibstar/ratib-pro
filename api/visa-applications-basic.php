<?php
/**
 * EN: Handles API endpoint/business logic in `api/visa-applications-basic.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/visa-applications-basic.php`.
 */
header('Content-Type: application/json');

// Initialize sample data storage
session_start();
if (!isset($_SESSION['sample_applications'])) {
    $_SESSION['sample_applications'] = [
        [
            'id' => 1,
            'worker_name' => 'John Doe',
            'passport_number' => 'A1234567',
            'nationality' => 'American',
            'date_of_birth' => '1990-01-15',
            'gender' => 'male',
            'visa_type_id' => 1,
            'status' => 'pending',
            'contact_number' => '+1234567890',
            'email' => 'john.doe@email.com',
            'address' => '123 Main St, New York, USA',
            'agent_id' => 1,
            'duration' => '12',
            'priority' => 'normal',
            'created_at' => '2025-01-16 10:00:00'
        ],
        [
            'id' => 2,
            'worker_name' => 'Jane Smith',
            'passport_number' => 'B9876543',
            'nationality' => 'British',
            'date_of_birth' => '1985-05-20',
            'gender' => 'female',
            'visa_type_id' => 2,
            'status' => 'approved',
            'contact_number' => '+44123456789',
            'email' => 'jane.smith@email.com',
            'address' => '456 Oak Ave, London, UK',
            'agent_id' => 2,
            'duration' => '6',
            'priority' => 'high',
            'created_at' => '2025-01-15 14:30:00'
        ],
        [
            'id' => 3,
            'worker_name' => 'Ahmed Ali',
            'passport_number' => 'C5555555',
            'nationality' => 'Egyptian',
            'date_of_birth' => '1988-12-10',
            'gender' => 'male',
            'visa_type_id' => 3,
            'status' => 'rejected',
            'contact_number' => '+20123456789',
            'email' => 'ahmed.ali@email.com',
            'address' => '789 Palm St, Cairo, Egypt',
            'agent_id' => 1,
            'duration' => '24',
            'priority' => 'urgent',
            'created_at' => '2025-01-14 09:15:00'
        ]
    ];
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'test':
        echo json_encode([
            'success' => true,
            'message' => 'Basic visa applications API is working',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'list':
        // Return current session data
        echo json_encode([
            'success' => true,
            'applications' => $_SESSION['sample_applications']
        ]);
        break;
        
    case 'stats':
        echo json_encode([
            'success' => true,
            'stats' => [
                'total' => 3,
                'pending' => 1,
                'approved' => 1,
                'rejected' => 1
            ]
        ]);
        break;
        
    case 'bulk_approve':
        $input = json_decode(file_get_contents('php://input'), true);
        $applicationIds = $input['application_ids'] ?? [];
        $approvalDate = $input['approval_date'] ?? date('Y-m-d');
        $approvalNotes = $input['approval_notes'] ?? '';
        
        // Update the status of selected applications
        foreach ($_SESSION['sample_applications'] as &$app) {
            if (in_array($app['id'], $applicationIds)) {
                $app['status'] = 'approved';
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Applications approved successfully',
            'approved_count' => count($applicationIds),
            'approval_date' => $approvalDate,
            'approval_notes' => $approvalNotes
        ]);
        break;
        
    case 'bulk_reject':
        $input = json_decode(file_get_contents('php://input'), true);
        $applicationIds = $input['application_ids'] ?? [];
        $rejectionDate = $input['rejection_date'] ?? date('Y-m-d');
        $rejectionReason = $input['rejection_reason'] ?? '';
        $rejectionNotes = $input['rejection_notes'] ?? '';
        
        // Update the status of selected applications
        foreach ($_SESSION['sample_applications'] as &$app) {
            if (in_array($app['id'], $applicationIds)) {
                $app['status'] = 'rejected';
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Applications rejected successfully',
            'rejected_count' => count($applicationIds),
            'rejection_date' => $rejectionDate,
            'rejection_reason' => $rejectionReason,
            'rejection_notes' => $rejectionNotes
        ]);
        break;
        
    case 'add':
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Generate new ID
        $newId = 1;
        if (!empty($_SESSION['sample_applications'])) {
            $newId = max(array_column($_SESSION['sample_applications'], 'id')) + 1;
        }
        
        // Create new application
        $newApplication = [
            'id' => $newId,
            'worker_name' => $input['worker_name'] ?? 'New Applicant',
            'passport_number' => $input['passport_number'] ?? '',
            'nationality' => $input['nationality'] ?? 'Unknown',
            'date_of_birth' => $input['date_of_birth'] ?? date('Y-m-d'),
            'gender' => $input['gender'] ?? 'male',
            'visa_type_id' => $input['visa_type_id'] ?? 1,
            'status' => $input['status'] ?? 'pending',
            'contact_number' => $input['contact_number'] ?? '',
            'email' => $input['email'] ?? '',
            'address' => $input['address'] ?? '',
            'agent_id' => $input['agent_id'] ?? 1,
            'duration' => $input['duration'] ?? 12,
            'priority' => $input['priority'] ?? 'normal',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Add to session data
        $_SESSION['sample_applications'][] = $newApplication;
        
        echo json_encode([
            'success' => true,
            'message' => 'Application added successfully',
            'application' => $newApplication
        ]);
        break;
        
    case 'update':
        $input = json_decode(file_get_contents('php://input'), true);
        $applicationId = $input['id'] ?? null;
        
        if ($applicationId) {
            // Find and update the application
            foreach ($_SESSION['sample_applications'] as &$app) {
                if ($app['id'] == $applicationId) {
                    $app['worker_name'] = $input['applicant_name'] ?? $app['worker_name'];
                    $app['passport_number'] = $input['passport_number'] ?? $app['passport_number'];
                    $app['nationality'] = $input['nationality'] ?? $app['nationality'];
                    $app['date_of_birth'] = $input['date_of_birth'] ?? $app['date_of_birth'];
                    $app['gender'] = $input['gender'] ?? $app['gender'];
                    $app['visa_type_id'] = $input['visa_type_id'] ?? $app['visa_type_id'];
                    $app['status'] = $input['status'] ?? $app['status'];
                    $app['contact_number'] = $input['phone'] ?? $app['contact_number'];
                    $app['email'] = $input['email'] ?? $app['email'];
                    $app['address'] = $input['notes'] ?? $app['address'];
                    $app['agent_id'] = $input['agent_id'] ?? $app['agent_id'];
                    $app['duration'] = $input['duration'] ?? $app['duration'];
                    $app['priority'] = $input['priority'] ?? $app['priority'];
                    break;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Application updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Application ID is required'
            ]);
        }
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        break;
}
?>
