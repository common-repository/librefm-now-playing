<?php

// this is here just to please Poedit.
function lfmnp_poo() {
	$lfmnp_plugin_name=__('Libre.fm Now Playing', 'lfmnp');
	$lfmnp_plugin_description=__("A widget to display your 'now playing' status from Libre.fm.", 'lfmnp');
}

/*
 *	Text Domain: lfmnp
 *	Plugin Name: Libre.fm Now Playing
 *	Plugin URI: http://mummila.net/nuudelisoppa/2010/12/09/libre-fm-now-playing-for-wordpress/
 *	Description: A widget to display your 'now playing' status from Libre.fm.
 *	Version: 1.0.1
 *	Author: Jani Uusitalo
 *	Author URI: http://mummila.net/
 *	License: GPLv2
 */

/*
	This program is free software; you can redistribute it and/or modify 
	it under the terms of the GNU General Public License as published by 
	the Free Software Foundation; version 2 of the License.

	This program is distributed in the hope that it will be useful, 
	but WITHOUT ANY WARRANTY; without even the implied warranty of 
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the 
	GNU General Public License for more details. 

	You should have received a copy of the GNU General Public License 
	along with this program; if not, write to the Free Software 
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA 
*/

class Librefm_Now_Playing extends WP_Widget {

	var $transient_np = 'librefm_now_playing';

	function Librefm_Now_Playing() {
		parent::WP_Widget(
			false,
			$name = __('Libre.fm Now Playing', 'lfmnp'),
			array( 'description' => __("Display your 'now playing' status from Libre.fm.", 'lfmnp') )
		);
	}

	// http://www.bobulous.org.uk/coding/php-5-xml-feeds.html
	function produce_XML_object_tree($raw_XML) {
	    libxml_use_internal_errors(true);
	    try {
		$xmlTree = new SimpleXMLElement($raw_XML);
	    } catch (Exception $e) {
		// Something went wrong.
		$error_message = 'SimpleXMLElement threw an exception.';
		foreach(libxml_get_errors() as $error_line) {
		    $error_message .= "\t" . $error_line->message;
		}
		trigger_error($error_message);
		return false;
	    }
	    return $xmlTree;
	}

	function fetch_np($username, $format, $linksong) {
		
		$uri = "http://alpha.libre.fm/2.0/?method=user.getrecenttracks&user=" . urlencode($username) . "&page=1&limit=1";

		$xml = file_get_contents($uri);
		if ($xml) {
			$xml_tree = $this->produce_XML_object_tree($xml);
			if ($xml_tree) {
				if ( !isset($xml_tree->recenttracks) ) {
					return false;
				}
				if ( !isset($xml_tree->recenttracks->track) ) {
					return false;
				}
				foreach ($xml_tree->recenttracks->track as $track) {
					foreach ($track->attributes() as $attribute => $val) {
						if ($attribute == 'nowplaying' && $val == 'true') {
							$artist = '';
							$trackname = '';
							$link = '';
							if ( isset($track->artist) ) {
								$artist = htmlspecialchars($track->artist);
							}
							if ( isset($track->name) ) {
								$trackname = htmlspecialchars($track->name);
							}
							if ( isset($track->url) ) {
								$link = $track->url;
							}

							$np = $format;
							$np = str_replace("%a", '<span class="librefm-now-playing-artist">'.$artist.'</span>', $np);
							$np = str_replace("%t", '<span class="librefm-now-playing-track">'.$trackname.'</span>', $np);
							if ( $linksong && $link ) {
								$nptitle = __('%a: %t on Libre.fm', 'lfmnp');
								$nptitle = str_replace("%a", $artist, $nptitle);
								$nptitle = str_replace("%t", $trackname, $nptitle);
								$np = '<a href="'.$link.'" title="'.$nptitle.'">'.$np.'</a>';
							}
							return $np;
						}
					}
				}
			}
		}
		return false;
	}

	function get_np($username, $format, $linksong) {
		$np = get_transient($this->transient_np);
//$np= false; // disable caching, for debugging
		if ( !$np ) {
			$np = $this->fetch_np($username, $format, $linksong);
			if ($np) {
				// transients api takes care of escaping $np
				// (ref: http://planetozh.com/blog/2010/05/overview-of-wordpress-transients-api/#)
				set_transient($this->transient_np, $np, 180);
			}
			else {
				delete_transient($this->transient_np);
			}
		}
		return $np;
	}

	// outputs the options form on admin
	function form($instance) {
		$defaults = array( 'title' => __('Now Playing', 'lfmnp'), 'lfmid' => '', 'format' => '%a: %t', 'linktitle' => false, 'linksong' => true, 'noplayhide' => true );
		$instance = wp_parse_args( (array) $instance, $defaults );

		$title = esc_attr($instance['title']);
		$lfmid = esc_attr($instance['lfmid']);
		$format = esc_attr($instance['format']);

		?>
		    <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'lfmnp'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
		    <p><label for="<?php echo $this->get_field_id('lfmid'); ?>"><?php _e('Your Libre.fm username:', 'lfmnp'); ?> <input class="widefat" id="<?php echo $this->get_field_id('lfmid'); ?>" name="<?php echo $this->get_field_name('lfmid'); ?>" type="text" value="<?php echo $lfmid; ?>" /></label></p>
		    <p><label for="<?php echo $this->get_field_id('format'); ?>"><?php printf( __('Formatting %s:', 'lfmnp'), '<small>'.__('(%a = artist, %t = track)', 'lfmnp').'</small>' ); ?> <input class="widefat" id="<?php echo $this->get_field_id('format'); ?>" name="<?php echo $this->get_field_name('format'); ?>" type="text" value="<?php echo $format; ?>" /></label></p>
		    <p><label for="<?php echo $this->get_field_id('links'); ?>"><?php _e('Link title to your profile:', 'lfmnp'); ?> <input class="widefat" id="<?php echo $this->get_field_id('linktitle'); ?>" name="<?php echo $this->get_field_name('linktitle'); ?>" <?php checked( $instance['linktitle'], 'on' ); ?> type="checkbox" /></label></p>
		    <p><label for="<?php echo $this->get_field_id('linksong'); ?>"><?php _e('Link track:', 'lfmnp'); ?> <input class="widefat" id="<?php echo $this->get_field_id('linksong'); ?>" name="<?php echo $this->get_field_name('linksong'); ?>" <?php checked( $instance['linksong'], 'on' ); ?> type="checkbox" /></label></p>
		    <p><label for="<?php echo $this->get_field_id('noplayhide'); ?>"><?php _e('Hide widget when not playing:', 'lfmnp'); ?> <input class="widefat" id="<?php echo $this->get_field_id('noplayhide'); ?>" name="<?php echo $this->get_field_name('noplayhide'); ?>" <?php checked( $instance['noplayhide'], 'on' ); ?> type="checkbox" /></label></p>
		<?php 
	}
	// processes widget options to be saved
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['lfmid'] = strip_tags($new_instance['lfmid']);
		$instance['format'] = strip_tags($new_instance['format']);
		$instance['linktitle'] = $new_instance['linktitle'];
		$instance['linksong'] = $new_instance['linksong'];
		$instance['noplayhide'] = $new_instance['noplayhide'];
		return $instance;
	}

	// outputs the content of the widget
	function widget($args, $instance) {
		extract( $args );
		$title = apply_filters('widget_title', $instance['title']);
		$lfmid = $instance['lfmid'];
		$format = $instance['format'];
		$linktitle = $instance['linktitle'];
		$linksong = $instance['linksong'];
		$noplayhide = $instance['noplayhide'];

		// don't even try if no username
		if ( $lfmid ) {
			$np = $this->get_np($lfmid, $format, $linksong);

			if ( $np || !$noplayhide ) {
				echo $before_widget;
				if ( $title ) {
					if ( $linktitle ) {
						echo $before_title.'<a href="http://libre.fm/user/'.$lfmid.'" title="'
							.sprintf( __('%s on Libre.fm', 'lfmnp'), $lfmid ).'">'.$title.'</a>'.$after_title;
					}
					else {
						echo $before_title.$title.$after_title;
					}
				}
				echo $np;
				echo $after_widget;
			}
		}
	}
}

add_action('widgets_init', create_function('', 'return register_widget("Librefm_Now_Playing");'));
load_plugin_textdomain('lfmnp', false, dirname(plugin_basename(__FILE__)).'/');
?>
