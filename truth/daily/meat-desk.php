<?php
declare(strict_types=1);

/**
 * Trust-Worthy Meat Desk
 *
 * These are not conclusions. They are high-consequence questions worth putting on trial
 * because the answer can materially affect how ordinary people live, spend, protect
 * themselves, understand institutions, evaluate belief, or make decisions.
 */
function tw_meat_desk_candidates(): array {
    $questions = [
        ['Money & power', 'How is new money actually created, who benefits first, and what does that do to ordinary people’s purchasing power?'],
        ['Money & power', 'Where do the fees, interest, penalties, subscriptions, and “convenience” charges in everyday life actually go — and which ones are avoidable?'],
        ['Privacy & technology', 'What data does your phone collect when you are not actively using an app, who can receive it, and what can that data reveal about you?'],
        ['Privacy & technology', 'What do AI systems, advertisers, data brokers, and platforms actually know or infer about you from ordinary online activity?'],
        ['Media & persuasion', 'How can the same verified facts be framed to make two audiences walk away believing opposite things?'],
        ['Media & persuasion', 'When hundreds of outlets repeat the same claim, how often are they independently confirming it versus repeating the same original source?'],
        ['Government & public record', 'What is the practical difference between what an official says, what a government record proves, and what the public is asked to infer?'],
        ['Government & public record', 'How much public policy is shaped by lobbying, contracting, campaign money, industry groups, and revolving-door employment — and what can actually be documented?'],
        ['Food & consumer claims', 'What do labels such as “natural,” “healthy,” “made with,” “no added sugar,” and similar marketing terms actually guarantee — and what do they not guarantee?'],
        ['Science & evidence', 'What is the difference between a single study, a replicated finding, an observational association, a causal result, and scientific consensus?'],
        ['Science & evidence', 'How often do dramatic scientific headlines say more than the underlying study actually established?'],
        ['History', 'How do we know that a famous historical event happened the way textbooks commonly describe it — and which parts rest on later retelling rather than contemporary evidence?'],
        ['Religion & scripture', 'Which widely repeated Christian teachings are explicitly recorded in the words of Jesus, and which are later theological frameworks built from other texts?'],
        ['Religion & scripture', 'How did translation choices, manuscript differences, church history, and cultural assumptions shape what modern readers think a biblical passage means?'],
        ['Work & consumer life', 'What rights, protections, warranties, cancellation rules, and contract terms do ordinary people routinely give up because they never read the fine print?'],
        ['Algorithms & attention', 'How do recommendation algorithms decide what you see, what you never see, and what keeps you emotionally engaged?'],
        ['War & conflict', 'When governments and media describe a war, strike, casualty count, or threat, what can be independently established and what depends on interested parties?'],
        ['Health information', 'How can a person distinguish a medical claim supported by strong evidence from one supported only by anecdotes, preliminary studies, marketing, or authority alone?'],
        ['Law & justice', 'What is the difference between being accused, charged, indicted, convicted, and proven responsible — and how often does public reporting blur those stages?'],
        ['Everyday truth', 'What common “everybody knows” belief would change the most decisions if people discovered it was incomplete, misleading, or unsupported?'],
    ];

    $out = [];
    foreach ($questions as $i => [$lane, $question]) {
        $out[] = [
            'id' => 'meat-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
            'source' => 'Trust-Worthy Meat Desk',
            'title' => $question,
            'url' => '',
            'published_at_utc' => gmdate('c'),
            'timestamp' => time() - $i,
            'summary' => 'Evergreen need-to-know question selected for broad real-world consequence, not headline novelty.',
            'score' => 150 - $i,
            'trial_lane' => $lane,
            'tension_score' => 50,
            'corroborating_sources' => [],
            'why_trial_worthy' => 'Need-to-know: the answer can materially affect ordinary decisions and beliefs.',
            'candidate_type' => 'meat-desk',
        ];
    }
    return $out;
}

function tw_user_question_candidates(string $privateDir, int $max = 6): array {
    $path = $privateDir . '/questions.json';
    if (!is_file($path)) return [];
    $rows = json_decode((string)file_get_contents($path), true);
    if (!is_array($rows)) return [];

    $out = [];
    foreach (array_reverse($rows) as $row) {
        if (!is_array($row)) continue;
        $question = trim((string)($row['question'] ?? ''));
        if ($question === '') continue;
        $submitted = strtotime((string)($row['submitted_at_utc'] ?? '')) ?: time();
        $out[] = [
            'id' => 'user-' . preg_replace('/[^a-zA-Z0-9]/', '', (string)($row['id'] ?? substr(hash('sha256', $question), 0, 16))),
            'source' => 'Private user question',
            'title' => $question,
            'url' => '',
            'published_at_utc' => gmdate('c', $submitted),
            'timestamp' => $submitted,
            'summary' => trim((string)($row['context'] ?? '')),
            'score' => 200 - count($out),
            'trial_lane' => 'People are asking',
            'tension_score' => 60,
            'corroborating_sources' => [],
            'why_trial_worthy' => 'A real person asked Trust-Worthy to investigate it.',
            'candidate_type' => 'user-question',
        ];
        if (count($out) >= $max) break;
    }
    return $out;
}

function tw_build_meat_queue(array $newsCandidates, string $privateDir, int $max = 18): array {
    $user = tw_user_question_candidates($privateDir, 6);
    $meat = array_slice(tw_meat_desk_candidates(), 0, 10);
    $news = array_slice($newsCandidates, 0, 4);

    $queue = array_merge($user, $meat, $news);
    return array_slice($queue, 0, $max);
}
