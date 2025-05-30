<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'cbe_rand_content';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.3';
$plugin['author'] = 'Claire Brione';
$plugin['author_uri'] = 'https://github.com/ClaireBrione/cbe_rand_content';
$plugin['description'] = 'Generate mass (fake) articles and comments';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '3';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '2';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

$plugin['textpack'] = <<< EOT
#@owner cbe_rand_content
#@admin
#@language en
cbe_rand_content_tab_label => Random content
cbe_rand_content_pop_com => Generate comments
cbe_rand_content_pop_art => Generate articles
cbe_rand_content_go_back => Back
cbe_rand_content_no_comm_allowed => Comments are not allowed
cbe_rand_content_populate => Populate !
cbe_rand_content_populate_end => Populating finished
cbe_rand_content_with_errors => with errors
#@language fr
cbe_rand_content_tab_label => Contenu aléatoire
cbe_rand_content_pop_com => Génération de commentaires
cbe_rand_content_pop_art => Génération d’articles
cbe_rand_content_go_back => Retour
cbe_rand_content_no_comm_allowed => Les commentaires ne sont pas autorisés
cbe_rand_content_populate => Générer !
cbe_rand_content_populate_end => Génération terminée
cbe_rand_content_with_errors => avec des erreurs
EOT;
// End of textpack

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
/**
 * cbe_rand_content - Generate mass (fake) articles and comments
 *
 * 0.1 - 25 May 2013 - Initial release
 * 0.2 - 21 Jul 2015 - http://forum.textpattern.com/viewtopic.php?pid=293584#p293584
 *                     preparing _cbe_rndc_init() and _cbe_rndc_reinit() if, one day...
 * 0.3 – 05 May 2025 - Compatibility updates for Textpattern v4.9 and PHP 8 (jools-r)
 *
 * @author  Claire Brione
 * @link    http://www.clairebrione.com/
 * @version 0.3
 */

/* =========================== Constants ============================ */
define( 'CBE_RNDC_VERSION', '0.3' ) ;               // Current version
define( 'CBE_RNDC_EVENT'  , 'cbe_rand_content' ) ;  // This event's name
define( 'CBE_RNDC_SPFX'   , 'cbe_rndc_'  ) ;        // Internal short prefix
define( 'CBE_RNDC_LPFX'   , CBE_RNDC_EVENT.'_' ) ;  // Internal long prefix

if( txpinterface === 'admin' ) {

  add_privs( CBE_RNDC_EVENT, '1, 2' ) ;
  register_tab( 'extensions', CBE_RNDC_EVENT, gTxt( CBE_RNDC_LPFX.'tab_label' ) ) ;
  register_callback( CBE_RNDC_LPFX.'lifecycle', 'plugin_lifecycle.'.CBE_RNDC_EVENT ) ;
  register_callback( CBE_RNDC_LPFX.'router' , CBE_RNDC_EVENT ) ;

/* ============================ Internal ============================ */
define( 'DIGITS'    , 0 ) ;
define( 'ALPHAMAJUS', 1 ) ;
define( 'ALPHAMINUS', 2 ) ;

function _cbe_rndc_minimax( &$mini, &$maxi )
{
	if( $maxi == 0 )
		$maxi = $mini ;

	if( $maxi < $mini ) {
		$a    = $mini ;
		$mini = $maxi ;
		$maxi = $a    ;
	}
}

/**
 *
 * @param  string $randword csv list of words to choose from
 * @param  int    $alphanum type
 *
 * @return string           random word
 */
function _cbe_rndc( $randword, $alphanum, $long_min, $long_max = 0 )
{
	$aCars = array( DIGITS     => "0123456789"
		      , ALPHAMAJUS => "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
		      , ALPHAMINUS => "aabcdeefghiijkalmenoopqrstuuvwaxyz"
		      ) ;

	if( ! in_array( $alphanum, array( DIGITS, ALPHAMAJUS, ALPHAMINUS ) ) )
		return ;

	if( $randword !== false && rand( 1, 100 ) == 50 ) {
		$aWords = explode( ",", $randword ) ;
		$word   = $aWords[ array_rand( $aWords ) ] ;
		return( $word ) ;
	}

	_cbe_rndc_minimax( $long_min, $long_max ) ;

	$long_fin = rand( $long_min, $long_max ) ;
	$randmax = strlen( $aCars[ $alphanum ] ) - 1 ;

	$word = '' ;
	for( $k = 0 ; $k < $long_fin ; $k++ )
		$word .= $aCars[ $alphanum ][ rand( 0, $randmax ) ] ;

	if( $alphanum == DIGITS && $word[0] == '0' )	
		$word[0] = $aCars[ $alphanum ][ rand( 1, $randmax ) ] ;

	return( $word ) ;
}

function _cbe_rndc_name( $long_min, $long_max = 0 )
{
	_cbe_rndc_minimax( $long_min, $long_max ) ;
	$out = array() ;
	$out[] = _cbe_rndc( false, ALPHAMAJUS, 1 ) ;
	$out[] = _cbe_rndc( false, ALPHAMINUS, $long_min-1, $long_max-1 ) ;
	return( join( "", $out ) ) ;
}

function _cbe_rndc_ip()
{
	$out = array() ;
	for( $i = 0 ; $i < 4 ; $i++ ) {
		$out[] = _cbe_rndc( false, DIGITS, 2, 3 ) ;
	}
	return( join( ".", $out ) ) ;
}

function _cbe_rndc_sentence( $word_min = 7, $word_max = 0, $char_min = 5, $char_max = 0 )
{
	_cbe_rndc_minimax( $word_min, $word_max ) ;
	_cbe_rndc_minimax( $char_min, $char_max ) ;
	$out = array() ;
	$mots = rand( $word_min, $word_max ) ;
	for( $i = 0 ; $i < $mots ; $i++ ) {
		$out[] = _cbe_rndc( 'lorem,ipsum,dolor', ALPHAMINUS, $char_min, $char_max ) ;
	}
	return( _cbe_rndc( false, ALPHAMAJUS, 1 ) . join( " ", $out ) . '.' ) ;
}

function _cbe_rndc_text( $sent_min = 5, $sent_max = 0, $word_min = 7, $word_max = 0 )
{
	_cbe_rndc_minimax( $sent_min, $sent_max ) ;
	_cbe_rndc_minimax( $word_min, $word_max ) ;
	$out = array() ;
	$phrases = rand( $sent_min, $sent_max ) ;
	for( $i = 0 ; $i < $phrases ; $i++ ) {
		$out[] = _cbe_rndc_sentence( $word_min, $word_max, 2, 8 ) ;
	}
	return( join( " ", $out ) ) ;
}
/* =========================== / Internal =========================== */

/* =================== Plugin's lifecycle related =================== */
/**
 * _cbe_rndc_init - Admin-side: plugin table creation, prefs insertion
 *
 * @access private
 */
  function _cbe_rndc_init()
  {
      // Nothing at the moment
      return ;
  }

/**
 * _cbe_rndc_reinit - Admin-side: plugin table update, prefs update
 *
 * @access private
 */
  function _cbe_rndc_reinit()
  {
      // Nothing at the moment
      return ;
  }

/**
 * cbe_rndc_lifecycle_installed - Admin-side: fires when plugin is installed
 *
 * @return  void
 */
  function cbe_rndc_lifecycle_installed()
  {
      $registered_version = get_pref( CBE_RNDC_LPFX.'version', 'none' ) ;

      if( $registered_version === CBE_RNDC_VERSION ) {
          return ;

      } elseif( $registered_version === 'none' ) {
          _cbe_rndc_init() ;

      } else {
          _cbe_rndc_reinit() ;
      }

      set_pref( CBE_RNDC_LPFX.'version', CBE_RNDC_VERSION, CBE_RNDC_EVENT, PREF_HIDDEN, '' ) ;

      return ;
  }

/**
 * cbe_rndc_lifecycle_enabled - Admin-side: fires when plugin is enabled
 *
 * @return  void
 */
  function cbe_rndc_lifecycle_enabled()
  {
      return( cbe_rndc_lifecycle_installed() ) ;
  }

/**
 * cbe_rndc_lifecycle_deleted - Admin-side: fires when plugin is deleted
 *
 * @return  void
 */
  function cbe_rndc_lifecycle_deleted()
  {
      safe_delete( 'txp_prefs', "event='".CBE_RNDC_EVENT."'" ) ;
      return ;
  }

/**
 * cbe_rand_content_lifecycle - Admin-side: plugin lifecycle
 *
 * @param   string $event admin event
 * @param   string $step  admin step
 * @return  void
 */
  function cbe_rand_content_lifecycle( $event = '', $step = '' )
  {
      if( $step && is_callable( $func = CBE_RNDC_SPFX."lifecycle_$step" ) )
          return( $func() ) ;

      return ;
  }

/* =================== Tab 'Extensions' ===================== */
/**
 * _cbe_rndc_initiate - Admin-side: First screen
 *
 * @return  array
 */
  function _cbe_rndc_initiate( &$message, &$html )
  {
      global $event ;
      $next_step = NULL ;
      $out = array() ;

      $out[] = form( hed( gTxt( CBE_RNDC_LPFX.'pop_art' ), 2 )
                   .n. tag( fInput( 'submit', 'submit', gTxt( CBE_RNDC_LPFX.'populate' ), 'smallerbox' )
                          .n. sInput( CBE_RNDC_SPFX.'pop_art' )
                          .n. eInput( $event )
                          , 'div' )
                   , '', "verify('".gTxt('are_you_sure')."')" ) ;

      $out[] = form( hed( gTxt( CBE_RNDC_LPFX.'pop_com' ), 2 )
                   .n. tag( fInput( 'submit', 'submit', gTxt( CBE_RNDC_LPFX.'populate' ), 'smallerbox' )
                          .n. sInput( CBE_RNDC_SPFX.'pop_com' )
                          .n. eInput( $event )
                          , 'div' )
                   , '', "verify('".gTxt('are_you_sure')."')" ) ;

      $html = join( '<hr>', $out ) ;
      return( $next_step ) ;
  }

/**
 * _cbe_rndc_pop_art - Admin-side: Generate articles
 *
 * See "Rules for articles" in the helpfile
 *
 * @return  array
 */
  function _cbe_rndc_pop_art( &$message, &$html )
  {
      global $event, $comments_on_default, $comments_default_invite ;
      $next_step = NULL ;
      $out = array() ;
      $globerrlevel = '' ;
      $message      = gTxt( CBE_RNDC_LPFX.'populate_end' ) ;
      if( ($use_textile = get_pref( 'use_textile' )) == USE_TEXTILE ) {
          $textile = new \Textpattern\Textile\Parser() ;
      }

      $authors     = safe_column_num( 'name', 'txp_users', "`privs`<6" ) ;
      $posauthor   = count( $authors ) - 1 ;

      $sections    = safe_column_num( 'name', 'txp_section', "`on_frontpage`=1 AND `name`!='default'" ) ;
      $possection  = count( $sections ) - 1 ;

      $categories  = safe_column_num( 'name', 'txp_category', " `name`!='root' AND `type`='article'" ) ;
      $poscategory = count( $categories ) - 1 ;

      $stati = array( STATUS_LIVE, STATUS_LIVE
                    , STATUS_DRAFT
                    , STATUS_LIVE, STATUS_LIVE
                    , STATUS_HIDDEN
                    , STATUS_LIVE, STATUS_LIVE
                    , STATUS_PENDING
                    , STATUS_LIVE, STATUS_LIVE
                    ) ;
      $posstatus = count( $stati ) - 1 ;

      $rndnb =  rand( 10, 15 ) ;
      $aAids = array() ;
      $errlevel = "success" ;

      for( $i = 0 ; $i < $rndnb ; $i++ ) {

          $seeddate  = rand( time()-(200*24*60*60), time()+(60*24*60*60) ) ;
          $in        = rand( 0, 9 ) ;
          $status    = $stati[ rand( 0, $posstatus ) ] ;
          $published = date( "Y-m-d H:i:s", $seeddate ) ;
          $lastmod   = date( "Y-m-d H:i:s" ) ;
          $expires   = ( in_array( $in, array( 0, 4, 8 ) ) ) ? null : date( "Y-m-d H:i:s", strtotime( "+{$in} months", $seeddate ) ) ;
          $feeddate  = date( "Y-m-d", $seeddate ) ;
          $author    = $authors[ rand( 0, $posauthor ) ] ;
          $section   = $sections[ rand( 0, $possection ) ] ;
          $category1 = $categories[ rand( 0, $poscategory ) ] ;
          if( ($category2 = $in == 0 ? '' : $categories[ rand( 0, $poscategory ) ]) == $category1 ) {
              $category2 = '' ;
          }
          $title     = substr( _cbe_rndc_sentence( 3, 5, 3, 6 ), 0, -1 ) ;
          $url_title = stripSpace( $title, 1 ) ;

          $excerpt   = _cbe_rndc_text( 6, 10, 2, 8 ) ;
          $arrbody = array() ;
          $parag = rand( 2, 5 ) ;
          for( $j = 0 ; $j < $parag ; $j++ ) {
              $arrbody[] .= _cbe_rndc_text( 6, 8, 5, 10 ) . n ;
          }
          $body = join( n, $arrbody ) ;

          switch( $use_textile ) {
          case USE_TEXTILE :
              $textile = new \Textpattern\Textile\Parser() ;
          //  $title        = $textile -> parse( $title, '', 1 ) ;
              $body_html    = $textile -> parse( $body ) ;
              $excerpt_html = $textile -> parse( $excerpt ) ;
              break ;

          case LEAVE_TEXT_UNTOUCHED :
              $body_html    = trim( $body ) ;
              $excerpt_html = trim( $excerpt ) ;
              break ;

          case CONVERT_LINEBREAKS :
              $body_html    = nl2br( trim( $body ) ) ;
              $excerpt_html = nl2br( trim( $excerpt ) ) ;
              break ;

          default :
              break ;
          }

          if( $insertd = safe_insert( "textpattern",
                                      "Title           = '$title',
                                       Body            = '$body',
                                       Body_html       = '$body_html',
                                       Excerpt         = '$excerpt',
                                       Excerpt_html    = '$excerpt_html',
                                       Status          = '$status',
                                       Posted          = '$published',
                                       Expires         = ". ($expires ? "'$expires'" : "NULL") . ",
                                       AuthorID        = '$author',
                                       LastMod         = '$lastmod',
                                       LastModID       = '$author',
                                       Section         = '$section',
                                       Category1       = '$category1',
                                       Category2       = '$category2',
                                       textile_body    =  $use_textile,
                                       textile_excerpt =  $use_textile,
                                       Annotate        =  $comments_on_default,
                                       url_title       = '".doSlash($url_title)."',
                                       AnnotateInvite  = '$comments_default_invite',
                                       uid             = '".md5(uniqid(rand(),true))."',
                                       feed_time       = '$feeddate'"
                                     ) ) {
              $aAids[] = $insertd ;

          } else {
              $errlevel     = "warning" ;
              $globerrlevel = E_ERROR   ;
          }
      }

      $out[] = graf( tag( gTxt( CBE_RNDC_LPFX.'populate_end' ), 'span', ' class="'.$errlevel.'"' )
                   . ': ' . join( ", ", $aAids ) ) ;

      if( ! empty( $globerrlevel ) )
          $message .= ' '. gTxt( CBE_RNDC_LPFX.'with_errors' ) ;

      $back  = tag( fInput( 'submit', 'submit', gTxt( CBE_RNDC_LPFX.'go_back' ), 'publish')
                    .n. sInput( CBE_RNDC_SPFX.'initiate' )
                    .n. eInput( $event )
                  , 'div' ) ;

      $html    = join( n, $out ) . form( $back ) ;
      return( $next_step ) ;
  }

/**
 * _cbe_rndc_pop_com - Admin-side: Generate comments
 *
 * See "Rules for comments" in the helpfile
 *
 * @return  array
 */
  function _cbe_rndc_pop_com( &$message, &$html )
  {
      global $event, $use_comments, $comments_disabled_after ;
      $next_step = NULL ;
      $out = array() ;
      $globerrlevel = '' ;
      $aIds = safe_rows( "ID, Title, unix_timestamp(Posted) as uPosted", "textpattern"
                       , "`Posted`<=now() AND
                          (`Expires`>now() OR `Expires`= NULL) AND
                          `Status`=".STATUS_LIVE." AND
                          `Annotate`='1'
                         ORDER BY ID" ) ;

      if( ! $use_comments ) {
          $globerrlevel = E_ERROR   ;
          $message      = gTxt( CBE_RNDC_LPFX.'no_comm_allowed' ) ;

      } else {
          $message = gTxt( CBE_RNDC_LPFX.'populate_end' ) ;
          if( ($lifespan = $comments_disabled_after * 86400) > 0 ) {
                function commentIsActive(&$v, $k, $p) {
                    $v[ "ID" ] = (time()-$v["uPosted"] < $p) ? $v["ID"] : false ;
                }
                function commentFilterActive($v) {
                    return($v[ "ID" ]) ;
                }
                array_walk($aIds, 'commentIsActive', $lifespan) ;
                $aIds = array_filter($aIds, 'commentFilterActive') ;
          }

          foreach( $aIds as $article ) {
              if( rand( 0, 99 ) == 50 )
                  continue ;

              $rndnb = rand( 3, 10 ) ;
              $aCids = array() ;
              $errlevel = "success" ;

              for( $i = 0 ; $i < $rndnb ; $i++ ) {

                  $comm = '' ;
                  $parag = rand( 2, 5 ) ;
                  for( $j = 0 ; $j < $parag ; $j++ ) {
                      $comm .= '<p>' . _cbe_rndc_text( 6, 10, 2, 8 ) . '</p>' ;
                  }

                  if( $insertd = safe_insert( "txp_discuss"
                                            , "`parentid`='{$article['ID']}',
                                               `name`='"     . doSlash( _cbe_rndc_name( 4, 7 ).' '._cbe_rndc_name( 4, 9 ) ) . "',
                                               `email`='"    . doSlash( _cbe_rndc( false, ALPHAMINUS, 4, 7 ).'@'
                                                                      . _cbe_rndc( false, ALPHAMINUS, 4, 9 ).'.'
                                                                      . _cbe_rndc( false, ALPHAMINUS, 3 ) )
                                                             . "',
                                               `web`='"      . doSlash( 'http://'._cbe_rndc( false, ALPHAMINUS, 4, 7 ).'.'
                                                                      . _cbe_rndc( false, ALPHAMINUS, 3 ) )
                                                             . "',
                                               `message`='"  . doSlash( $comm ) . "',
                                               `posted`='"   . doSlash( date( "Y-m-d H:i:s", rand( $article["uPosted"], time() ) ) )
                                                             . "'"
                                            ) ) {
                      $aCids[] = $insertd ;

                  } else {
                      $errlevel     = "warning" ;
                      $globerrlevel = E_ERROR   ;
                  }
              }

              update_comments_count( $article[ "ID" ] ) ;

              $out[] = graf( tag( $article['Title'], 'span', ' class="'.$errlevel.'"' ) . ': ' . join( ", ", $aCids ) ) ;
          }

          if( ! empty( $globerrlevel ) ) {
              $message .= ' '. gTxt( CBE_RNDC_LPFX.'with_errors' ) ;
          }
      }

      $back = tag( fInput( 'submit', 'submit', gTxt( CBE_RNDC_LPFX.'go_back' ), 'publish')
                    .n. sInput( CBE_RNDC_SPFX.'initiate' )
                    .n. eInput( $event )
                  , 'div' ) ;

      $html = join( n, $out ) . form( $back ) ;
      return( $next_step ) ;
  }

/**
 * cbe_rand_content_router - Admin-side: Entry point
 *
 * @param   string $event admin event
 * @param   string $step  admin step
 * @return  void
 */
  function cbe_rand_content_router( $event, $step )
  {
      global $step, $event ;
      require_privs( $event ) ;

      ${CBE_RNDC_SPFX.'message'} = '' ;
      ${CBE_RNDC_SPFX.'html'}    = '' ;

      $available_steps = array( CBE_RNDC_SPFX.'initiate' => false
                              , CBE_RNDC_SPFX.'pop_art'  => true
                              , CBE_RNDC_SPFX.'pop_com'  => true
                              ) ;

      if( ! $step ) {
          $step = CBE_RNDC_SPFX.'initiate' ;
      }

      while( $step && bouncer( $step, $available_steps ) && is_callable( $func = "_$step" ) ) {
          $step = $func( ${CBE_RNDC_SPFX.'message'}, ${CBE_RNDC_SPFX.'html'} ) ;
      }

      pagetop( gTxt( CBE_RNDC_LPFX.'tab_label' ), ${CBE_RNDC_SPFX.'message'} ) ;

      echo ( hed( gTxt( CBE_RNDC_LPFX.'tab_label' ), '1', ' class="txp-heading"' ) 
           . tag( ${CBE_RNDC_SPFX.'html'}, 'div', ' class="text-column"' )
           ) ;

      return ;
  }
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
h1. cbe_rand_content

h2. Features

A minimalist, but functional admin-side plugin to populate your content tables. If you need content to test your front-end, this plugin will generate random articles and comments for you.

Only one problem, though: they are written in Martian, with sometimes a very little bit of lorem ipsum. But even on Mars, sentences begin with an uppercase and end with a period, names begin with an uppercase.

Chain articles and their comments generation (already existing articles will be left untouched).

Rules for articles and comments are customizable in the functions @_cbe_rndc_pop_art@ and @_cbe_rndc_pop_com@:

Rules for articles:

* between 10 and 15 articles are generated at a time
* the probability for an article to be live is 8 over 11 (nearly 72%)
* publish date is somewhere between 200 days ago and 60 days ahead
* sometimes, expiration date is a few months after publish date
* sometimes, an article has no category2
* title: 3 to 5 words, 3 to 6 characters each word
* excerpt: 1 paragraph, 6 to 10 words, 2 to 8 characters each word
* body: 2 to 5 paragraphs, 6 to 8 sentences each paragraph, 5 to 10 words each paragraph, 2 to 8 characters each word
* keywords and custom fields are *not* populated

Rules for comments:

* generation will fail if comments are not used
* only for non-expired live articles
* sometimes, a non-expired live article won't receive any comment
* between 3 and 10 comments are generated for each article
* per comment: 2 to 5 paragraphs, 6 to 10 words each paragraph, 2 to 8 characters each word
* name: 2 words, 4 to 7 characters and 4 to 9 characters


h2. Installation / requirements

Copy/paste the txt-installer in the Admin > Plugins tab to install, then activate.

Although there is no risk of damage, it is strongly suggested to export your @textpattern@ and @txp_discuss@ tables first.

Version 0.3 tested with Textpattern 4.9 and PHP8.


h2. Usage

Switch to the _Extensions › Random Content_ tab.

You can then choose to generate articles and/or generate comments.

A list if the new article IDs will be displayed when the process is complete.

To generate more content, just run it as many times as you wish.

Note: To generate comments, you must set *Accept comments: yes* on the _Admin › Preferences › Site_ panel. Then in the new _Admin › Preferences › Comments_ panel, switch on *Comments on by default*. It is best to set this before you generate articles, as this determines whether the generated articles can have comments.


h2. Changelog

* 25 May 2013 - 0.1 - Initial release
* 21 July 2015 - 0.2 - Fixed "fatal error":http://forum.textpattern.com/viewtopic.php?pid=293584#p293584 and preparing @_cbe_rndc_init()@ and @_cbe_rndc_reinit()@ if, one day...
* 05 May 2025 - 0.3 - Compatibility updates for Textpattern v4.9 and PHP 8 (jools-r)
# --- END PLUGIN HELP ---
-->
<?php
}
?>