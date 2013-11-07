<?php
/**
 * Fetch from Twitter Command
 */

namespace Zoltar\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TTools\App;

class FetchCommand extends Command {

    protected function configure()
    {
        $this
            ->setName('zoltar:fetch')
            ->setDescription('Fetch and process mentions')
            ->addArgument(
                'tweet_id',
                InputArgument::OPTIONAL,
                'Make Zoltar answer a specific tweet'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cacheDir  = __DIR__ . '/../../../app/data';
        $cacheFile = $cacheDir . '/lastTweet.id';

        $config = $this->getApplication()->getService('config');

        $credentials = $config['credentials'];
        $answers = $config['answers'];

        $appConfig = array(
            'consumer_key'          => $credentials['consumer_key'],
            'consumer_secret'       => $credentials['consumer_secret'],
            'access_token'          => $credentials['access_token'],
            'access_token_secret'   => $credentials['access_token_secret']
        );

        $twitterApp = new App($appConfig);

        $params = array('count' => 1);

        $since_id = 0;
        if (is_file($cacheFile)) {
            $since_id = file_get_contents($cacheFile);
        }

        if ($since_id) {
            $params['since_id'] = $since_id;
        }
        if (!is_writable($cacheDir)) {
            $output->writeln("<error>Cache file is not writable. Please fix the permissions (we need to save the last answered tweet id, otherwise we will answer the same questions over and over).</error>");
            return 0;
        }

        $mentions = $twitterApp->get('/statuses/mentions_timeline.json', $params);

        $count = 0;
        $mention = [];

        if (isset($mentions['error'])) {
            $output->writeln("<error>" . $mentions['error_message'] . "</error>");
            return 0;
        }

        if (count($mentions)) {
            foreach ($mentions as $mention) {
                if ($this->isQuestion($mention['text'])) {
                    $answer = $answers[array_rand($answers)];

                    $output->writeln('<info>' . $mention['text'] . '</info>');

                    $author = $mention['user']['screen_name'];

                    /** tweet answer */
                    $twitterApp->update("@$author $answer", $mention['id_str']);

                    $output->writeln('<info>Answered: ' . $answer . '</info>');

                    $count++;
                }

            }

            $lastTweetId = isset($mention['id_str']) ? $mention['id_str'] : null;

            if ($lastTweetId) {

                $cache = fopen($cacheFile, 'w+');
                fwrite($cache, $lastTweetId);
                fclose($cache);
                $output->writeln("<info>Saved last tweet id ". $lastTweetId . "</info>");
                $output->writeln("<info>$count tweets answered.</info>");

            }
        } else {
            $output->writeln("<info>No new mentions found.</info>");
        }

    }

    private function isQuestion($string)
    {
        if(strpos($string, '?') === false)
            return false;
        else
            return true;
    }
} 