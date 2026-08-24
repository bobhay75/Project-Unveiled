<?php
declare(strict_types=1);

return [
    'feeds' => [
        ['name' => 'BBC News', 'url' => 'https://feeds.bbci.co.uk/news/rss.xml', 'weight' => 1.0],
        ['name' => 'NPR', 'url' => 'https://feeds.npr.org/1001/rss.xml', 'weight' => 1.0],
        ['name' => 'Fox News', 'url' => 'https://moxie.foxnews.com/google-publisher/latest.xml', 'weight' => 1.0],
        ['name' => 'CNN', 'url' => 'http://rss.cnn.com/rss/cnn_topstories.rss', 'weight' => 1.0],
        ['name' => 'NBC News', 'url' => 'https://feeds.nbcnews.com/nbcnews/public/news', 'weight' => 1.0],
        ['name' => 'CBS News', 'url' => 'https://www.cbsnews.com/latest/rss/main', 'weight' => 1.0],
        ['name' => 'ABC News', 'url' => 'https://abcnews.go.com/abcnews/topstories', 'weight' => 1.0],
        ['name' => 'New York Times', 'url' => 'https://rss.nytimes.com/services/xml/rss/nyt/HomePage.xml', 'weight' => 1.0],
        ['name' => 'Al Jazeera', 'url' => 'https://www.aljazeera.com/xml/rss/all.xml', 'weight' => 1.0],
    ],
    'max_items_per_feed' => 18,
    'max_candidates' => 30,
    'lookback_hours' => 36,
    'openai_model' => getenv('TRUST_WORTHY_OPENAI_MODEL') ?: 'gpt-5.6-luna',
];
