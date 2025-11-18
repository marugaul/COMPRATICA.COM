<?php
/**
 * Script para actualizar las imágenes de los servicios existentes
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$pdo = db();

// Mapeo de slug a URL de imagen
$images = [
    'consultoria-legal-civil' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=600&h=400&fit=crop',
    'abogado-laboral-trabajadores' => 'https://images.unsplash.com/photo-1505664194779-8beaceb93744?w=600&h=400&fit=crop',
    'tramites-migratorios' => 'https://images.unsplash.com/photo-1521587760476-6c12a4b040da?w=600&h=400&fit=crop',
    'reparacion-electrodomesticos' => 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=600&h=400&fit=crop',
    'plomeria-profesional' => 'https://images.unsplash.com/photo-1607472586893-edb57bdc0e39?w=600&h=400&fit=crop',
    'electricista-residencial' => 'https://images.unsplash.com/photo-1621905251189-08b45d6a269e?w=600&h=400&fit=crop',
    'tutoria-matematicas' => 'https://images.unsplash.com/photo-1596495578065-6e0763fa1178?w=600&h=400&fit=crop',
    'ingles-conversacional' => 'https://images.unsplash.com/photo-1546410531-bb4caa6b424d?w=600&h=400&fit=crop',
    'programacion-web' => 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=600&h=400&fit=crop',
    'flete-pickup' => 'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?w=600&h=400&fit=crop',
    'mudanzas-completas' => 'https://images.unsplash.com/photo-1600518464441-9154a4dea21b?w=600&h=400&fit=crop',
    'transporte-materiales' => 'https://images.unsplash.com/photo-1581094271901-8022df4466f9?w=600&h=400&fit=crop',
    'tour-playa-samara' => 'https://images.unsplash.com/photo-1559827260-dc66d52bef19?w=600&h=400&fit=crop',
    'shuttle-aeropuerto-sjo' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=600&h=400&fit=crop',
    'shuttle-aeropuerto-lir' => 'https://images.unsplash.com/photo-1464037866556-6812c9d1c72e?w=600&h=400&fit=crop'
];

$updated = 0;
$errors = [];

foreach ($images as $slug => $imageUrl) {
    try {
        $stmt = $pdo->prepare("UPDATE services SET cover_image = ? WHERE slug = ?");
        $stmt->execute([$imageUrl, $slug]);

        if ($stmt->rowCount() > 0) {
            $updated++;
            echo "✅ Actualizado: $slug\n";
        } else {
            echo "⚠️  No encontrado: $slug\n";
        }
    } catch (Exception $e) {
        $errors[] = "Error en $slug: " . $e->getMessage();
        echo "❌ Error en $slug: " . $e->getMessage() . "\n";
    }
}

echo "\n==============================================\n";
echo "RESUMEN:\n";
echo "   • $updated servicios actualizados\n";
echo "   • " . count($errors) . " errores\n";
echo "==============================================\n";

if (count($errors) > 0) {
    echo "\nErrores:\n";
    foreach ($errors as $err) {
        echo "   • $err\n";
    }
}
