<?php

namespace AppBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleListener implements EventSubscriberInterface {
    private $defaultLocale = 'en';

    public function __construct($defaultLocale = 'en') {
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(GetResponseEvent $event) {
        $request = $event->getRequest();

        $language = $request->getPreferredLanguage();

        if($language === null) {
            $language = $this->defaultLocale;
        }

        $request->setLocale($language);
    }

    public static function getSubscribedEvents() {
        return [
            KernelEvents::REQUEST => [ [ 'onKernelRequest', 17 ]]
        ];
    }
}