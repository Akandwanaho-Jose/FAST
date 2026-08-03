<?php

declare(strict_types=1);

$database=require dirname(__DIR__).'/bootstrap/database.php';$connection=$database->connection();$result=['research_units_by_status'=>[],'published_units'=>0,'current_members'=>0,'current_leads'=>0,'projects'=>0,'publications'=>0];
foreach($connection->query('SELECT status,COUNT(*) total FROM research_units WHERE deleted_at IS NULL GROUP BY status')as$row)$result['research_units_by_status'][$row['status']]=(int)$row['total'];
$result['published_units']=(int)$connection->query('SELECT COUNT(*) FROM research_units WHERE status="published" AND published_at IS NOT NULL AND published_at<=NOW() AND deleted_at IS NULL')->fetchColumn();
$result['current_members']=(int)$connection->query('SELECT COUNT(*) FROM research_unit_members WHERE is_current=1 AND (start_date IS NULL OR start_date<=CURDATE()) AND (end_date IS NULL OR end_date>=CURDATE())')->fetchColumn();
$result['current_leads']=(int)$connection->query('SELECT COUNT(*) FROM research_unit_members WHERE membership_role="lead" AND is_current=1 AND (start_date IS NULL OR start_date<=CURDATE()) AND (end_date IS NULL OR end_date>=CURDATE())')->fetchColumn();
$result['projects']=(int)$connection->query('SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL')->fetchColumn();$result['publications']=(int)$connection->query('SELECT COUNT(*) FROM publications WHERE deleted_at IS NULL')->fetchColumn();
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
