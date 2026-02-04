<?php
/**
 * Execution Tracking System - نظام متابعة التنفيذ
 * v1.0 - Track progress, checkpoints, and achievements
 *
 * نظام متقدم لمتابعة تنفيذ الخطط وقياس التقدم
 */

class ExecutionTrackingSystem {

    private $db;
    private $diagnosticId;
    private $clientId;

    public function __construct($diagnosticId) {
        $this->db = db();
        $this->diagnosticId = $diagnosticId;

        $diagnostic = $this->db->fetchOne("SELECT client_id FROM diagnostic_results WHERE id = ?", [$diagnosticId]);
        $this->clientId = $diagnostic['client_id'] ?? null;
    }

    /**
     * إنشاء خطة تنفيذ جديدة
     */
    public function createExecutionPlan($planData) {
        $planId = $this->db->insert('execution_plans', [
            'diagnostic_id' => $this->diagnosticId,
            'client_id' => $this->clientId,
            'plan_type' => $planData['type'], // '90day', 'quarterly', 'annual'
            'start_date' => $planData['start_date'] ?? date('Y-m-d'),
            'end_date' => $planData['end_date'],
            'status' => 'active',
            'plan_data' => json_encode($planData['plan'], JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // إنشاء المهام (Tasks)
        if (!empty($planData['tasks'])) {
            $this->createTasks($planId, $planData['tasks']);
        }

        // إنشاء نقاط التفتيش (Checkpoints)
        if (!empty($planData['checkpoints'])) {
            $this->createCheckpoints($planId, $planData['checkpoints']);
        }

        // إنشاء KPIs
        if (!empty($planData['kpis'])) {
            $this->createKPIs($planId, $planData['kpis']);
        }

        return $planId;
    }

    /**
     * إنشاء المهام
     */
    private function createTasks($planId, $tasks) {
        foreach ($tasks as $task) {
            $this->db->insert('execution_tasks', [
                'plan_id' => $planId,
                'client_id' => $this->clientId,
                'task_id' => $task['id'] ?? uniqid('task_'),
                'title' => $task['title'],
                'description' => $task['description'] ?? '',
                'priority' => $task['priority'] ?? 'medium',
                'status' => 'pending',
                'due_date' => $task['due_date'] ?? null,
                'estimated_duration' => $task['duration'] ?? null,
                'responsible' => $task['responsible'] ?? null,
                'phase' => $task['phase'] ?? 1,
                'task_data' => json_encode($task, JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * إنشاء نقاط التفتيش
     */
    private function createCheckpoints($planId, $checkpoints) {
        foreach ($checkpoints as $checkpoint) {
            $this->db->insert('execution_checkpoints', [
                'plan_id' => $planId,
                'client_id' => $this->clientId,
                'checkpoint_date' => $checkpoint['date'],
                'checkpoint_type' => $checkpoint['type'], // 'weekly', 'monthly', 'quarterly'
                'status' => 'upcoming',
                'duration_minutes' => $checkpoint['duration'] ?? 30,
                'agenda' => json_encode($checkpoint['agenda'] ?? [], JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * إنشاء KPIs
     */
    private function createKPIs($planId, $kpis) {
        foreach ($kpis as $kpi) {
            $this->db->insert('execution_kpis', [
                'plan_id' => $planId,
                'client_id' => $this->clientId,
                'kpi_name' => $kpi['name'],
                'kpi_category' => $kpi['category'] ?? 'general',
                'current_value' => $kpi['current'] ?? 0,
                'target_value' => $kpi['target'],
                'unit' => $kpi['unit'] ?? '%',
                'measurement_frequency' => $kpi['frequency'] ?? 'weekly',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * تحديث حالة مهمة
     */
    public function updateTaskStatus($taskId, $status, $notes = null) {
        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($status === 'completed') {
            $updateData['completed_at'] = date('Y-m-d H:i:s');
        }

        if ($notes) {
            $updateData['notes'] = $notes;
        }

        $this->db->update('execution_tasks', $updateData, ['id' => $taskId]);

        // تسجيل في السجل
        $this->logActivity('task_updated', [
            'task_id' => $taskId,
            'status' => $status,
            'notes' => $notes
        ]);

        // إرسال تنبيه إذا كانت مهمة مكتملة
        if ($status === 'completed') {
            $this->sendNotification('task_completed', $taskId);
        }
    }

    /**
     * تحديث قيمة KPI
     */
    public function updateKPIValue($kpiId, $newValue, $notes = null) {
        $kpi = $this->db->fetchOne("SELECT * FROM execution_kpis WHERE id = ?", [$kpiId]);

        if (!$kpi) {
            throw new Exception('KPI not found');
        }

        // حساب التقدم
        $progress = $this->calculateKPIProgress($kpi['current_value'], $newValue, $kpi['target_value']);

        $this->db->update('execution_kpis', [
            'current_value' => $newValue,
            'progress_percentage' => $progress,
            'last_measured_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $kpiId]);

        // حفظ في السجل التاريخي
        $this->db->insert('kpi_measurements', [
            'kpi_id' => $kpiId,
            'measured_value' => $newValue,
            'progress_percentage' => $progress,
            'notes' => $notes,
            'measured_at' => date('Y-m-d H:i:s')
        ]);

        // إرسال تنبيه إذا وصلنا للهدف
        if ($progress >= 100) {
            $this->sendNotification('kpi_achieved', $kpiId);
        }
    }

    /**
     * إكمال نقطة تفتيش
     */
    public function completeCheckpoint($checkpointId, $outcomes, $nextSteps) {
        $this->db->update('execution_checkpoints', [
            'status' => 'completed',
            'outcomes' => json_encode($outcomes, JSON_UNESCAPED_UNICODE),
            'next_steps' => json_encode($nextSteps, JSON_UNESCAPED_UNICODE),
            'completed_at' => date('Y-m-d H:i:s')
        ], ['id' => $checkpointId]);

        // إنشاء نقطة التفتيش القادمة
        $this->scheduleNextCheckpoint($checkpointId);
    }

    /**
     * الحصول على لوحة التحكم التنفيذية
     */
    public function getExecutionDashboard($planId) {
        $plan = $this->db->fetchOne("SELECT * FROM execution_plans WHERE id = ?", [$planId]);

        if (!$plan) {
            throw new Exception('Plan not found');
        }

        return [
            'plan' => $plan,
            'overall_progress' => $this->calculateOverallProgress($planId),
            'tasks' => $this->getTasksSummary($planId),
            'kpis' => $this->getKPIsSummary($planId),
            'checkpoints' => $this->getUpcomingCheckpoints($planId),
            'recent_activities' => $this->getRecentActivities($planId),
            'alerts' => $this->getAlerts($planId),
            'achievements' => $this->getAchievements($planId)
        ];
    }

    /**
     * حساب التقدم الإجمالي
     */
    private function calculateOverallProgress($planId) {
        // التقدم في المهام
        $tasksProgress = $this->db->fetchOne("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM execution_tasks
            WHERE plan_id = ?
        ", [$planId]);

        // التقدم في KPIs
        $kpisProgress = $this->db->fetchOne("
            SELECT AVG(progress_percentage) as avg_progress
            FROM execution_kpis
            WHERE plan_id = ?
        ", [$planId]);

        $taskProgress = ($tasksProgress['total'] > 0)
            ? ($tasksProgress['completed'] / $tasksProgress['total']) * 100
            : 0;

        $kpiProgress = $kpisProgress['avg_progress'] ?? 0;

        // متوسط مرجح (60% مهام، 40% KPIs)
        $overallProgress = ($taskProgress * 0.6) + ($kpiProgress * 0.4);

        return [
            'percentage' => round($overallProgress, 1),
            'tasks_completed' => $tasksProgress['completed'],
            'tasks_total' => $tasksProgress['total'],
            'kpis_avg_progress' => round($kpiProgress, 1),
            'status' => $this->getProgressStatus($overallProgress)
        ];
    }

    /**
     * ملخص المهام
     */
    private function getTasksSummary($planId) {
        $tasks = $this->db->fetchAll("
            SELECT status, priority, COUNT(*) as count
            FROM execution_tasks
            WHERE plan_id = ?
            GROUP BY status, priority
        ", [$planId]);

        $summary = [
            'by_status' => [],
            'by_priority' => [],
            'overdue' => $this->getOverdueTasks($planId),
            'upcoming' => $this->getUpcomingTasks($planId)
        ];

        foreach ($tasks as $task) {
            $summary['by_status'][$task['status']] = ($summary['by_status'][$task['status']] ?? 0) + $task['count'];
            $summary['by_priority'][$task['priority']] = ($summary['by_priority'][$task['priority']] ?? 0) + $task['count'];
        }

        return $summary;
    }

    /**
     * ملخص KPIs
     */
    private function getKPIsSummary($planId) {
        $kpis = $this->db->fetchAll("
            SELECT * FROM execution_kpis
            WHERE plan_id = ?
            ORDER BY progress_percentage DESC
        ", [$planId]);

        $summary = [
            'total' => count($kpis),
            'on_track' => 0,
            'at_risk' => 0,
            'achieved' => 0,
            'details' => []
        ];

        foreach ($kpis as $kpi) {
            $progress = $kpi['progress_percentage'] ?? 0;

            if ($progress >= 100) {
                $summary['achieved']++;
            } elseif ($progress >= 70) {
                $summary['on_track']++;
            } else {
                $summary['at_risk']++;
            }

            $summary['details'][] = [
                'id' => $kpi['id'],
                'name' => $kpi['kpi_name'],
                'current' => $kpi['current_value'],
                'target' => $kpi['target_value'],
                'unit' => $kpi['unit'],
                'progress' => $progress,
                'status' => $this->getKPIStatus($progress)
            ];
        }

        return $summary;
    }

    /**
     * نقاط التفتيش القادمة
     */
    private function getUpcomingCheckpoints($planId) {
        return $this->db->fetchAll("
            SELECT * FROM execution_checkpoints
            WHERE plan_id = ?
            AND status = 'upcoming'
            AND checkpoint_date >= CURDATE()
            ORDER BY checkpoint_date ASC
            LIMIT 5
        ", [$planId]);
    }

    /**
     * الأنشطة الأخيرة
     */
    private function getRecentActivities($planId) {
        return $this->db->fetchAll("
            SELECT * FROM execution_activity_log
            WHERE plan_id = ?
            ORDER BY created_at DESC
            LIMIT 20
        ", [$planId]);
    }

    /**
     * التنبيهات
     */
    private function getAlerts($planId) {
        $alerts = [];

        // مهام متأخرة
        $overdue = $this->db->fetchOne("
            SELECT COUNT(*) as count
            FROM execution_tasks
            WHERE plan_id = ?
            AND status != 'completed'
            AND due_date < CURDATE()
        ", [$planId]);

        if ($overdue['count'] > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "لديك {$overdue['count']} مهمة متأخرة",
                'action' => 'view_overdue_tasks'
            ];
        }

        // KPIs متعثرة
        $atRiskKPIs = $this->db->fetchOne("
            SELECT COUNT(*) as count
            FROM execution_kpis
            WHERE plan_id = ?
            AND progress_percentage < 50
        ", [$planId]);

        if ($atRiskKPIs['count'] > 0) {
            $alerts[] = [
                'type' => 'danger',
                'message' => "{$atRiskKPIs['count']} مقياس أداء في خطر",
                'action' => 'review_kpis'
            ];
        }

        // نقطة تفتيش قريبة
        $upcomingCheckpoint = $this->db->fetchOne("
            SELECT * FROM execution_checkpoints
            WHERE plan_id = ?
            AND status = 'upcoming'
            AND checkpoint_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY checkpoint_date ASC
            LIMIT 1
        ", [$planId]);

        if ($upcomingCheckpoint) {
            $alerts[] = [
                'type' => 'info',
                'message' => "نقطة تفتيش قادمة في {$upcomingCheckpoint['checkpoint_date']}",
                'action' => 'prepare_checkpoint'
            ];
        }

        return $alerts;
    }

    /**
     * الإنجازات
     */
    private function getAchievements($planId) {
        $achievements = [];

        // مهام مكتملة مؤخراً
        $recentCompleted = $this->db->fetchAll("
            SELECT * FROM execution_tasks
            WHERE plan_id = ?
            AND status = 'completed'
            AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY completed_at DESC
        ", [$planId]);

        if (count($recentCompleted) > 0) {
            $achievements[] = [
                'type' => 'tasks_completed',
                'count' => count($recentCompleted),
                'title' => count($recentCompleted) . ' مهمة مكتملة هذا الأسبوع',
                'icon' => 'check-circle'
            ];
        }

        // KPIs محققة
        $achievedKPIs = $this->db->fetchAll("
            SELECT * FROM execution_kpis
            WHERE plan_id = ?
            AND progress_percentage >= 100
        ", [$planId]);

        if (count($achievedKPIs) > 0) {
            $achievements[] = [
                'type' => 'kpis_achieved',
                'count' => count($achievedKPIs),
                'title' => count($achievedKPIs) . ' مقياس أداء تم تحقيقه',
                'icon' => 'trophy'
            ];
        }

        return $achievements;
    }

    // ==================== Helper Functions ====================

    private function calculateKPIProgress($oldValue, $newValue, $targetValue) {
        if ($targetValue == 0) return 0;

        $progress = ($newValue / $targetValue) * 100;
        return min(100, max(0, $progress));
    }

    private function getProgressStatus($progress) {
        if ($progress >= 90) return 'excellent';
        if ($progress >= 70) return 'on_track';
        if ($progress >= 40) return 'needs_attention';
        return 'at_risk';
    }

    private function getKPIStatus($progress) {
        if ($progress >= 100) return 'achieved';
        if ($progress >= 70) return 'on_track';
        if ($progress >= 40) return 'behind';
        return 'at_risk';
    }

    private function getOverdueTasks($planId) {
        return $this->db->fetchAll("
            SELECT * FROM execution_tasks
            WHERE plan_id = ?
            AND status != 'completed'
            AND due_date < CURDATE()
            ORDER BY due_date ASC
        ", [$planId]);
    }

    private function getUpcomingTasks($planId) {
        return $this->db->fetchAll("
            SELECT * FROM execution_tasks
            WHERE plan_id = ?
            AND status = 'pending'
            AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY due_date ASC
        ", [$planId]);
    }

    private function scheduleNextCheckpoint($checkpointId) {
        $checkpoint = $this->db->fetchOne("SELECT * FROM execution_checkpoints WHERE id = ?", [$checkpointId]);

        if (!$checkpoint) return;

        // حساب التاريخ القادم بناءً على النوع
        $nextDate = date('Y-m-d', strtotime($checkpoint['checkpoint_date'] . ' +1 ' . $checkpoint['checkpoint_type']));

        $this->db->insert('execution_checkpoints', [
            'plan_id' => $checkpoint['plan_id'],
            'client_id' => $checkpoint['client_id'],
            'checkpoint_date' => $nextDate,
            'checkpoint_type' => $checkpoint['checkpoint_type'],
            'status' => 'upcoming',
            'duration_minutes' => $checkpoint['duration_minutes'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function logActivity($activityType, $data) {
        try {
            $this->db->insert('execution_activity_log', [
                'plan_id' => $data['plan_id'] ?? null,
                'client_id' => $this->clientId,
                'activity_type' => $activityType,
                'activity_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }

    private function sendNotification($notificationType, $relatedId) {
        // سيتم تطوير نظام الإشعارات لاحقاً
        // يمكن إرسال إشعارات عبر البريد، SMS، أو Push Notifications
    }
}
