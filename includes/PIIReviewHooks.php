<?php

class PIIReviewHooks {
    public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
        if ( $out->getConfig()->get( 'PIIReviewKioskMode' ) ) {
            $out->addModules( 'ext.PIIReview' );
        }
        return true;
    }
}
