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
                'tweetId',
                InputArgument::OPTIONAL,
                'Make Zoltar answer a specific tweet'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cacheDir  = __DIR__ . '/../../../app/data';
        $cacheFile = $cacheDir . '/lastTweet.id';

        $singleTweet = false;
        $queryPath   = '/statuses/mentions_timeline.json';
        $params      = array('count' => 20);

        $tweetId = $input->getArgument('tweetId');
        if ($tweetId) {
            $singleTweet = true;
            $queryPath   = '/statuses/show.json';
            $params      = array('id' => $tweetId);
        }

        $config = $this->getApplication()->getService('config');
        $credentials = $config['credentials'];

        $appConfig = array(
            'consumer_key'          => $credentials['consumer_key'],
            'consumer_secret'       => $credentials['consumer_secret'],
            'access_token'          => $credentials['access_token'],
            'access_token_secret'   => $credentials['access_token_secret']
        );

        $twitterApp = new App($appConfig);

        if (!is_writable($cacheDir)) {
            $output->writeln("<error>Cache file is not writable. Please fix the permissions (we need to save the last answered tweet id, otherwise we will answer the same questions over and over).</error>");

            return 0;
        }

        if (!$singleTweet) {
            $since_id = 0;
            if (is_file($cacheFile)) {
                $since_id = file_get_contents($cacheFile);
            }

            if ($since_id) {
                $params['since_id'] = $since_id;
            }
        }

        $content = $twitterApp->get($queryPath, $params);

        $count = 0;
        $mention = [];

        if (isset($content['error'])) {
            $output->writeln("<error>" . $content['error_message'] . "</error>");

            return 0;
        }

        if ($singleTweet) {
            $this->answerTweet($content, $twitterApp, $output);
        } else {

            if (!count($content)) {
                $output->writeln("<info>No new mentions found.</info>");
                return 1;
            }

            foreach ($content as $mention) {
                if ($this->isQuestion($mention['text'])) {
                    $this->answerTweet($mention, $twitterApp, $output);
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

            return 1;
        }
    }

    private function answerTweet($tweet, App $twitterApp, OutputInterface $output)
    {
        $config  = $this->getApplication()->getService('config');
        $answers = $config['answers'];

        $answer = $answers[array_rand($answers)];

        $output->writeln("<info>Answering tweet " . $tweet['id_str'] . "...</info>");

        $author = $tweet['user']['screen_name'];

        /** tweet answer */
        $twitterApp->update("@$author $answer", $tweet['id_str']);
    }

    private function isQuestion($string)
    {
        if(strpos($string, '?') === false)
            return false;
        else
            return true;
    }
} 