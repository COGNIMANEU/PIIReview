( function ( mw, $ ) {
    'use strict';

    mw.PIIReview = {
        init: function () {
            var files = mw.config.get( 'wgPIIReviewFiles' );
            this.createInterface( files );
        },

        createInterface: function ( files ) {
            var container = document.getElementById( 'piireview-container' );
            if ( !container ) {
                return;
            }

            // Clear loading message
            container.innerHTML = '';

            files.forEach( function ( file ) {
                var reviewCard = this.createReviewCard( file );
                container.appendChild( reviewCard );
            }.bind( this ) );
        },

        createReviewCard: function ( file ) {
            var card = document.createElement( 'div' );
            card.className = 'piireview-card';

            // Preview
            if ( file.type.startsWith( 'image/' ) ) {
                var img = document.createElement( 'img' );
                img.src = 'data:' + file.type + ';base64,' + this.getFileContents( file.path );
                card.appendChild( img );
            } else if ( file.type.startsWith( 'video/' ) ) {
                var video = document.createElement( 'video' );
                video.controls = true;
                video.src = 'data:' + file.type + ';base64,' + this.getFileContents( file.path );
                card.appendChild( video );
            }

            // Review controls
            var controls = document.createElement( 'div' );
            controls.className = 'piireview-controls';

            var approveBtn = new OO.ui.ButtonWidget( {
                label: mw.msg( 'piireview-approve' ),
                flags: [ 'progressive' ]
            } );

            var rejectBtn = new OO.ui.ButtonWidget( {
                label: mw.msg( 'piireview-reject' ),
                flags: [ 'destructive' ]
            } );

            controls.$element.append(
                approveBtn.$element,
                rejectBtn.$element
            );

            card.appendChild( controls.$element[0] );

            return card;
        },

        getFileContents: function ( path ) {
            // Implementation would need server-side support to securely read files
            // This is a placeholder
            return '';
        }
    };

    $( function () {
        mw.PIIReview.init();
    } );

}( mediaWiki, jQuery ) );

