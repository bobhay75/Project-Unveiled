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
        ['Space & evidence', 'What evidence independently verifies the Apollo Moon landings, what are the strongest objections raised against them, and which claims survive serious examination?'],
        ['NASA & institutions', 'What has NASA actually claimed, documented, measured, photographed, contracted, and disclosed — and where can its major claims be independently checked rather than accepted on authority alone?'],
        ['Antarctica', 'What does the Antarctic Treaty System actually prohibit, restrict, and permit — including tourism, research, military activity, resource extraction, aircraft, and access to protected areas?'],
        ['Antarctica', 'What is actually known beneath Antarctica’s ice from radar, drilling, seismic work, satellites, ice cores, and subglacial exploration — and which extraordinary claims lack direct evidence?'],
        ['Antarctica', 'Why are some Antarctic locations difficult or restricted to access, who controls those restrictions, and are the documented reasons scientific, environmental, logistical, strategic, or something else?'],
        ['Holiday origins', 'Where did America’s major holidays and holiday customs actually come from — and which parts are documented history, older tradition, later reinterpretation, commerce, or myth?'],
        ['Traditions of men', 'Which beliefs, rules, rituals, titles, customs, and institutions commonly presented as Christian can be traced directly to Jesus and the earliest followers — and which developed later?'],
        ['Traditions of men', 'For a modern Christian teaching or practice, when does it first appear in the historical record, who formalized it, what influenced it, and did Jesus actually teach it?'],
        ['Modern traditions of men', 'Where did the modern altar call come from, when did it become standard in evangelical churches, and is anything equivalent explicitly taught or practiced by Jesus and the earliest followers?'],
        ['Modern traditions of men', 'Where did the modern sinner’s prayer formula come from, what passages are used to support it, and did Jesus present salvation as repeating a prescribed prayer?'],
        ['Modern traditions of men', 'When did the idea that every Christian must give exactly ten percent of income to a local church become a widespread modern requirement, and how does that compare with Jesus, Torah tithes, and earliest-church giving?'],
        ['Modern traditions of men', 'Where did the modern pre-tribulation rapture system come from, when was it popularized, and how does its timeline compare with the earliest Christian writings and the sayings attributed to Jesus?'],
        ['Modern traditions of men', 'How did prosperity-gospel teaching develop, what texts are used to support wealth-as-faith claims, and how does that compare with Jesus’ teaching about money, suffering, generosity, and power?'],
        ['Modern traditions of men', 'How did modern celebrity-pastor and megachurch culture develop, and how does its authority structure compare with the leadership patterns described in the earliest Christian communities?'],
        ['Modern traditions of men', 'When did church membership covenants, formal membership classes, and institutional loyalty requirements become common, and what biblical authority is actually claimed for them?'],
        ['Modern traditions of men', 'How did modern purity-culture rules develop, which parts come from scripture, which come from later social customs, and what effects have those teachings produced?'],
        ['Modern traditions of men', 'How did contemporary deliverance, spiritual-warfare, and “generational curse” systems develop, and which claims are directly grounded in Jesus’ teaching versus later frameworks?'],
        ['Modern traditions of men', 'How did “speaking in tongues” become a required or expected proof of Spirit baptism in some modern churches, and how does that doctrine compare with the New Testament texts used to support it?'],
        ['Modern traditions of men', 'Where did modern end-times charts, prophecy conferences, date-setting cultures, and nation-by-nation prophetic mapping come from, and what did Jesus actually tell followers to expect or watch for?'],
        ['Modern traditions of men', 'How did Christian nationalism and the idea that a modern nation has a special covenant with God develop, and how does that compare with Jesus’ teaching about the Kingdom of God?'],
        ['Modern traditions of men', 'How did modern church growth strategy, branding, stage production, worship-industry culture, and attendance metrics come to define “successful ministry,” and what standards did Jesus use for fruit and faithfulness?'],
        ['Modern traditions of men', 'How did the expectation that one paid senior pastor functions as the primary spiritual authority of a congregation become normal, and how does that compare with first-century leadership structures?'],
        ['Modern traditions of men', 'How did modern denominational statements of faith become tests of Christian belonging, and which required beliefs can be traced to Jesus versus later creeds, councils, and institutional disputes?'],
        ['Modern traditions of men', 'How did modern political-party identity become intertwined with Christian identity in the United States, and where does that alignment agree or conflict with the recorded teachings of Jesus?'],
        ['Jesus & calling', 'When Jesus said “many are called, but few are chosen,” what did “called” and “chosen” mean in the immediate parable, first-century setting, and the wider record of his teaching?'],
        ['Jesus & salvation', 'Are all people called by God, and did Jesus teach that everyone will ultimately enter the Kingdom, or did he describe a real final exclusion?'],
        ['God & religions', 'When different religions use the word “God,” are they making claims about the same ultimate reality, different conceptions of one reality, or genuinely different beings — and how can the claims be compared without assuming the answer?'],
        ['Spirit & flesh', 'What did Isaiah mean by “the Egyptians are men, and not God; and their horses flesh, and not spirit,” and what does the historical and literary context actually say about flesh, spirit, human power, and trust in God?'],
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
    $meat = array_slice(tw_meat_desk_candidates(), 0, 12);
    $news = array_slice($newsCandidates, 0, 2);

    $queue = array_merge($user, $meat, $news);
    return array_slice($queue, 0, $max);
}
