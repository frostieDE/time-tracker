<?php

namespace AppBundle\Utils;

class Slugger {
    public function slugify($string) {
        $slugger = new \Easybook\Slugger();
        return $slugger->slugify($string);
    }
}