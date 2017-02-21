<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/Buzz/Service.php';

$config = require_once __DIR__ . '/config.php';

$url = $config['api_url'] . 'site/premieres';

$date_from = new DateTime('next thursday', new \DateTimeZone('Europe/Kiev'));
$date_to = clone $date_from;
$date_to->modify('+6 days');

$mp = Mixpanel::getInstance($config['mixpanel_project_token']);

// build and call query

$home = new Buzz\Service('cinema');

$movies = $home->call('site/premieres', [
    'date_from' => $date_from->format('Y-m-d'),
    'date_to' => $date_to->format('Y-m-d'),
]);

if (empty($movies)) {
    die('nothing to show');
}

$message = '';

foreach ($movies as $movie) {

    $message .= "<a href='http://kinoafisha.ua/ua/films/{$movie->alias}'>{$movie->title}</a>" . PHP_EOL;

    if (!empty($movie->genres)) {
        $genres = [];
        foreach ($movie->genres as $genre) {
            $genres[] = $genre->title;
        }

        $message .= implode(', ', $genres) . PHP_EOL;
    }

    $message .= PHP_EOL;
}

echo($message);

$bot = new \TelegramBot\Api\Client($config['bot_token'], $config['bot_tracker']);

$states_dir = __DIR__ . '/states/';

$states = scandir($states_dir);

foreach ($states as $state) {

    $state = "{$states_dir}{$state}";
    if (!is_file($state)) continue;

    $state = json_decode(file_get_contents($state), true);
    if (empty($state)) continue;

    $chat_id = $state['id'];
    $user_name = @$state['chat']['first_name'];

    echo("$chat_id $user_name " . PHP_EOL);

    $user_message = "Привіт, {$user_name}, ось прем'єри цього тижня:\n\n{$message}";

    $bot->sendMessage($chat_id, $user_message, 'HTML', true);

    // track

    $mp->track('telegram_message_sent', [
        'subject' => 'premieres',
        'chat_id' => $chat_id,
    ]);

}