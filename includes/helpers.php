<?php
// Small view helpers shared by the result pages.

// Hiring steps a candidate can be in
const CANDIDATE_STATUSES = ['Yeni', 'Mülakata Çağrıldı', 'Teklif Verildi', 'İşe Alındı', 'Reddedildi'];

function scoreLevel(?float $score): string
{
    if ($score === null) {
        return 'none';
    }
    if ($score >= 70) {
        return 'high';
    }
    return $score >= 40 ? 'medium' : 'low';
}

function scoreBadge(?float $score): string
{
    $text = $score === null ? 'Puanlanmadı' : '%' . number_format($score, 1, ',', '');
    return '<span class="badge badge-' . scoreLevel($score) . '">' . $text . '</span>';
}

function parseSkills(string $requirements): array
{
    // Same rule as ml/features.py: split on commas, new lines, ; and /
    $parts = preg_split('/[,\n;\/]+/', strtolower($requirements));
    return array_values(array_filter(array_map('trim', $parts), 'strlen'));
}

function educationLabel(int $level): string
{
    return ['Bulunamadı', 'Lisans', 'Yüksek Lisans', 'Doktora'][$level] ?? 'Bulunamadı';
}

// Decision for a candidate score (same limits as scoreLevel)
function decisionLabel(float $score): string
{
    return [
        'high' => 'Kısa Listeye Alındı',
        'medium' => 'Değerlendirilebilir',
        'low' => 'Elendi',
    ][scoreLevel($score)];
}

// Ring chart that shows a 0-100 score
function scoreGauge(float $score): string
{
    $radius = 80;
    $circle = 2 * M_PI * $radius;
    $offset = $circle * (1 - $score / 100); // the empty part of the ring
    return '<div class="gauge">'
        . '<svg viewBox="0 0 190 190" width="190" height="190" aria-hidden="true">'
        . '<circle class="gauge-track" cx="95" cy="95" r="' . $radius . '"/>'
        . '<circle class="gauge-fill gauge-' . scoreLevel($score) . '" cx="95" cy="95" r="' . $radius . '"'
        . ' stroke-dasharray="' . round($circle, 2) . '" stroke-dashoffset="' . round($offset, 2) . '"/>'
        . '</svg>'
        . '<div class="gauge-text"><b>%' . round($score) . '</b><span>uyum</span></div>'
        . '</div>';
}

// SQLite saves dates in UTC; show them in Turkey's time
function localDateTime(string $utc): string
{
    $date = new DateTime($utc, new DateTimeZone('UTC'));
    return $date->setTimezone(new DateTimeZone('Europe/Istanbul'))->format('d.m.Y H:i');
}
