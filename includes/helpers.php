<?php
// Small view helpers shared by the result pages.

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
    $text = $score === null ? 'Not scored' : number_format($score, 1) . '%';
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
    return ['Not found', 'Bachelor', 'Master', 'PhD'][$level] ?? 'Not found';
}
