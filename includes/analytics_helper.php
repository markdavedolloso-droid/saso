<?php

// Provides the transparent keyword-based analysis shared by analytics views.

function analyzeComments(array $comments): array
{
    $text = strtolower(
        implode(' ', array_filter($comments))
    );

    $count = count(
        array_filter(
            $comments,
            fn($value) => trim((string) $value) !== ''
        )
    );

    $groups = [
        'strengths' => [
            'clear explanations' => [
                'clear',
                'explains well',
                'easy to understand',
                'understandable'
            ],
            'subject knowledge' => [
                'knowledgeable',
                'knows the subject',
                'mastery',
                'expert'
            ],
            'approachability' => [
                'approachable',
                'friendly',
                'helpful',
                'supportive',
                'support'
            ],
            'engagement' => [
                'engaging',
                'interactive',
                'interesting',
                'activities'
            ],
        ],

        'improvements' => [
            'lesson pacing' => [
                'too fast',
                'fast',
                'too quickly',
                'too quick',
                'pacing',
                'more time',
                'too slow'
            ],
            'more examples/practice' => [
                'more examples',
                'examples',
                'practice',
                'exercise',
                'activities'
            ],
            'communication/questions' => [
                'questions',
                'clarify',
                'clarification',
                'communication',
                'explain more'
            ],
            'classroom management' => [
                'classroom management',
                'discipline',
                'noise',
                'control'
            ],
        ],
    ];

    $hits = function (array $set) use ($text): array {
        $out = [];

        foreach ($set as $label => $words) {
            $number = 0;

            foreach ($words as $word) {
                $number += substr_count($text, $word);
            }

            if ($number > 0) {
                $out[$label] = $number;
            }
        }

        arsort($out);

        return $out;
    };

    $positive = [
        'good',
        'great',
        'excellent',
        'helpful',
        'friendly',
        'clear',
        'understand',
        'supportive',
        'engaging',
        'interesting',
        'amazing',
        'effective'
    ];

    $negative = [
        'bad',
        'poor',
        'confusing',
        'unclear',
        'fast',
        'slow',
        'difficult',
        'noise',
        'unfair',
        'improve',
        'improvement',
        'problem',
        'hard'
    ];

    $positiveCount = $negativeCount = 0;

    foreach ($positive as $word) {
        $positiveCount += substr_count($text, $word);
    }

    foreach ($negative as $word) {
        $negativeCount += substr_count($text, $word);
    }

    $sentiment = $positiveCount > $negativeCount
        ? 'Generally Positive'
        : (
            $negativeCount > $positiveCount
                ? 'Needs Attention'
                : 'Mixed/Neutral'
        );

    return [
        'count' => $count,
        'strengths' => $hits($groups['strengths']),
        'improvements' => $hits($groups['improvements']),
        'positive' => $positiveCount,
        'negative' => $negativeCount,
        'sentiment' => $sentiment
    ];
}