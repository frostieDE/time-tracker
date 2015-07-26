<?php

namespace AppBundle\Twig;

class Gravatar extends \Twig_Extension {
    public function getName() {
        return 'gravatar';
    }

    public function getFilters() {
        return [
            new \Twig_SimpleFilter('gravatar', [ $this, 'gravatarUrl' ])
        ];
    }

    public function gravatarUrl($email, $https = true) {
        $url = 'http://www.gravatar.com/';

        if($https === true) {
            $url = 'https://secure.gravatar.com/';
        }

        $url .= 'avatar/';
        $url .= md5(strtolower($email));
        $url .= '?s=40&d=mm';

        return $url;
    }
}