#!/usr/bin/env php
<?php
/**
 * Auto-categoriza empleos importados basándose en palabras clave
 * Ejecutar manualmente o agregar a cron después de import_jobs.php
 */

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

// Mapeo de palabras clave a categorías
$categoryKeywords = [
    'EMP:Technology' => [
        'developer', 'engineer', 'software', 'programmer', 'coding', 'frontend', 'backend',
        'fullstack', 'devops', 'data scientist', 'machine learning', 'ai', 'cloud',
        'javascript', 'python', 'java', 'php', 'react', 'node', 'mobile', 'ios', 'android',
        'tech', 'it support', 'sysadmin', 'database', 'qa', 'tester', 'security', 'cyber'
    ],
    'EMP:Sales' => [
        'sales', 'business development', 'account manager', 'account executive',
        'sales rep', 'sales manager', 'commercial', 'revenue', 'partnership'
    ],
    'EMP:Marketing' => [
        'marketing', 'digital marketing', 'seo', 'sem', 'content', 'social media',
        'brand', 'growth', 'analytics', 'campaign', 'advertising', 'pr', 'communications'
    ],
    'EMP:Design' => [
        'designer', 'ux', 'ui', 'graphic', 'creative', 'illustrator', 'product design',
        'visual', 'brand designer', 'art director'
    ],
    'EMP:Customer Service' => [
        'customer service', 'customer support', 'help desk', 'client success',
        'customer success', 'support specialist', 'customer care'
    ],
    'EMP:Administration' => [
        'admin', 'administrative', 'office manager', 'executive assistant',
        'coordinator', 'operations', 'hr', 'human resources', 'recruiter', 'talent'
    ],
    'EMP:Finance' => [
        'accountant', 'finance', 'financial', 'controller', 'cfo', 'bookkeeper',
        'accounting', 'analyst', 'budget', 'audit'
    ],
    'EMP:Legal' => [
        'lawyer', 'attorney', 'legal', 'paralegal', 'counsel', 'compliance',
        'legal advisor', 'legal assistant'
    ],
    'EMP:Management' => [
        'manager', 'director', 'head of', 'chief', 'ceo', 'cto', 'coo', 'president',
        'vp', 'vice president', 'lead', 'team lead', 'supervisor'
    ],
    'EMP:Education' => [
        'teacher', 'instructor', 'professor', 'educator', 'tutor', 'trainer',
        'coach', 'teaching', 'education'
    ],
    'EMP:Health' => [
        'doctor', 'nurse', 'medical', 'healthcare', 'health', 'physician',
        'therapist', 'clinical', 'pharmacist', 'dentist'
    ],
    'EMP:Hospitality' => [
        'hotel', 'restaurant', 'chef', 'cook', 'waiter', 'bartender', 'hospitality',
        'tourism', 'travel', 'front desk', 'concierge'
    ],
    'EMP:Construction' => [
        'construction', 'builder', 'contractor', 'architect', 'engineer', 'civil',
        'electrician', 'plumber', 'carpenter', 'foreman'
    ],
];

// Obtener empleos importados sin categoría específica o con categoría genérica
$stmt = $pdo->query("
    SELECT id, title, category, import_source
    FROM job_listings
    WHERE import_source IS NOT NULL
    AND (category IS NULL OR category = '' OR category = 'EMP:Technology')
    AND is_active = 1
");

$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Procesando " . count($jobs) . " empleos...\n\n";

$categorized = 0;
$updateStmt = $pdo->prepare("UPDATE job_listings SET category = ? WHERE id = ?");

foreach ($jobs as $job) {
    $title = strtolower($job['title']);
    $bestCategory = 'EMP:Technology'; // Default
    $maxScore = 0;

    // Calcular score para cada categoría
    foreach ($categoryKeywords as $category => $keywords) {
        $score = 0;
        foreach ($keywords as $keyword) {
            if (strpos($title, strtolower($keyword)) !== false) {
                // Palabras más largas tienen mayor peso
                $score += strlen($keyword);
            }
        }

        if ($score > $maxScore) {
            $maxScore = $score;
            $bestCategory = $category;
        }
    }

    // Solo actualizar si cambió la categoría
    if ($job['category'] !== $bestCategory) {
        $updateStmt->execute([$bestCategory, $job['id']]);
        echo "✓ [{$job['id']}] {$job['title']}\n";
        echo "  → {$bestCategory}\n\n";
        $categorized++;
    }
}

echo "\n======================================\n";
echo "Total categorizados: {$categorized}\n";
echo "======================================\n";

// Mostrar resumen por categoría
$summary = $pdo->query("
    SELECT category, COUNT(*) as count
    FROM job_listings
    WHERE import_source IS NOT NULL
    AND is_active = 1
    GROUP BY category
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo "\nResumen por categoría:\n";
foreach ($summary as $row) {
    $catName = str_replace('EMP:', '', $row['category']);
    echo sprintf("  %-20s: %d empleos\n", $catName, $row['count']);
}
