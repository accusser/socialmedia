<?php

/**
 * Social Media
 *
 * Copyright 2019 by Oene Tjeerd de Bruin <oenetjeerd@sterc.nl>
 */

require_once __DIR__ . '/twitter.class.php';
require_once dirname(dirname(__DIR__)) . '/socialmediasource.class.php';

class SocialMediaSourceTwitter extends SocialMediaSource
{
    /**
     * @access public.
     * @var String.
     */
    public $name = 'Twitter';

    /**
     * @access public.
     * @param Array $credentials.
     */
    public function setSource(array $credentials = [])
    {
        $this->source = new Twitter($this->modx, $credentials);
    }

    /**
     * @access public.
     * @param String $criteria.
     * @param Array $credentials.
     * @param Integer $limit.
     * @return Array.
     */
    public function getData($criteria, array $credentials = [], $limit = 10)
    {
        $source = $this->getSource($credentials);

        if ($source) {
            if (strpos($criteria, '@') === 0) {
                if (in_array($criteria, ['@me', '@self'], true)) {
                    $parameters = [
                        'count' => $limit
                    ];
                } else {
                    $parameters = [
                        'screen_name'       => substr($criteria, 1),
                        'count'             => $limit
                    ];
                }

                $responseMessages = $source->getApiData('statuses/user_timeline', $parameters);

                if ((int) $responseMessages['code'] === 200) {
                    $output = [];

                    foreach ((array) $responseMessages['data'] as $data) {
                        $output[] = $this->getFormat($data);
                    }

                    return $this->setResponse($responseMessages['code'], $this->getDataSort($output));
                }

                return $this->setResponse($responseMessages['code'], $responseMessages['message']);
            }

            if (strpos($criteria, '#') === 0) {
                $parameters = [
                    'q'             => $criteria,
                    'count'         => $limit,
                    'result_type'   => 'recent'
                ];

                $responseMessages = $source->getApiData('search/tweets', $parameters);

                if ((int) $responseMessages['code'] === 200) {
                    $output = [];

                    foreach ((array) $responseMessages['data'] as $data) {
                        $output[] = $this->getFormat($data);
                    }

                    return $this->setResponse($responseMessages['code'], $this->getDataSort($output));
                }

                return $this->setResponse($responseMessages['code'], $responseMessages['message']);
            }

            return $this->setResponse(500, 'API criteria method not supported.');
        }

        return $this->setResponse(500, 'API credentials not supported.');
    }

    /**
     * @access private.
     * @param Array $data.
     * @return Array.
     */
    private function getFormat(array $data = [])
    {
        $userImage  = '';
        $content    = '';
        $image      = '';
        $video      = '';
        $likes      = 0;
        $comments   = 0;

        if (isset($data['user']['profile_image_url'])) {
            $userImage = str_replace(['https:', 'http:', '_normal'], ['', '', '_200x200'], $data['user']['profile_image_url']);
        }

        if (isset($data['text'])) {
            $content = $data['text'];
        }

        if (isset($data['entities']['media'])) {
            foreach ((array) $data['entities']['media'] as $media) {
                if ($media['type'] === 'photo') {
                    $image      = str_replace(['https:', 'http:'], '', $media['media_url']);
                    $content    = str_replace($media['url'], '', $content);
                }
            }
        }

        if (isset($data['extended_entities']['media'])) {
            foreach ((array) $data['extended_entities']['media'] as $type) {
                if ($type['type'] === 'video') {
                    if (isset($type['video_info']['variants'])) {
                        foreach ((array) $type['video_info']['variants'] as $media) {
                            if ($media['content_type'] === 'video/mp4') {
                                $video      = str_replace(['https:', 'http:'], '', $media['url']);
                                $content    = str_replace($media['url'], '', $content);

                                break;
                            }
                        }
                    }
                }
            }
        }

        if (isset($data['favorite_count'])) {
            $likes = (int) $data['favorite_count'];
        }

        if (isset($data['retweet_count'])) {
            $comments = (int) $data['retweet_count'];
        }

        return [
            'key'           => $data['id'],
            'source'        => strtolower($this->getName()),
            'user_name'     => $this->getEmojiFormat($data['user']['name']),
            'user_account'  => $this->getEmojiFormat($data['user']['screen_name']),
            'user_image'    => $this->getImageFormat($userImage),
            'user_url'      => 'https://www.twitter.com/' . $data['user']['screen_name'],
            'content'       => $this->getEmojiFormat($content),
            'image'         => $this->getImageFormat($image),
            'video'         => $video,
            'url'           => 'https://www.twitter.com/' .$data['user']['screen_name'] . '/status/' . $data['id'],
            'likes'         => $likes,
            'comments'      => $comments,
            'created'       => date('Y-m-d H:i:s', strtotime($data['created_at']))
        ];
    }

    /**
     * @access public.
     * @param String $content.
     * @return String.
     */
    public function getHtmlFormat($content)
    {
        $content = preg_replace('/@(\w+)/', '<a href="https://twitter.com/\\1" target="_blank">@\\1</a>', $content);
        $content = preg_replace('/#(\w+)/', '<a href="https://twitter.com/search?q=\\1" target="_blank">#\\1</a>', $content);

        return parent::getHtmlFormat($content);
    }
}
