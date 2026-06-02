<?php
namespace IntentTarget\Core;
if ( ! defined( 'ABSPATH' ) ) exit;
class Intents {
	public static function get_categories(): array {
		return apply_filters( 'lee_dev_intent_categories_3812', array(
			'transactional-intent' => 'Transactional Intent',
			'informational-intent' => 'Informational Intent',
			'engagement-intent'    => 'Engagement Intent',
			'commercial-intent'    => 'Commercial Intent',
			'specialist-intent'    => 'Specialist Intent',
		) );
	}
	public static function classify_phrase( string $phrase ): string {
		$phrase = strtolower( trim( $phrase ) );
		if ( $phrase === '' ) return 'specialist-intent';
		$priority = apply_filters( 'lee_dev_intent_classifier_priority_8264', array(
			'transactional-intent','commercial-intent','engagement-intent','informational-intent',
		) );
		$lexicon = self::get_signal_lexicon();
		foreach ( $priority as $intent_slug ) {
			if ( empty( $lexicon[$intent_slug] ) ) continue;
			foreach ( $lexicon[$intent_slug] as $signal ) {
				$pattern = '/(^|\b)' . preg_quote( strtolower( trim( (string) $signal ) ), '/' ) . '(\b|$)/u';
				if ( preg_match( $pattern, $phrase ) === 1 ) return $intent_slug;
			}
		}
		return 'specialist-intent';
	}
	public static function get_signal_lexicon(): array {
		return apply_filters( 'lee_dev_intent_signal_lexicon_4275', array(
			'transactional-intent' => array('buy','order','purchase','shop','checkout','hire','rent','lease','book','sale','discount','coupon','price','quote','subscribe','delivery','payment','finance','gift card'),
			'commercial-intent' => array('best','top','compare','review','rated','recommend','options','premium','certified','trusted'),
			'engagement-intent' => array('contact','enquire','support','newsletter','join','member','event','webinar','workshop','register','social'),
			'informational-intent' => array('how','what','why','when','where','who','guide','tutorial','learn','tips','faq','examples','checklist'),
			'specialist-intent' => array(),
		) );
	}
	public static function get_scanner_blacklist(): array {
		$default = array('uncategorised','uncategorized','exclude-from-catalog','exclude-from-search','featured','format-standard','category','tag','the','and','for','with','from','this','that','your','will','have','are','was','were','their','they','them','our','we','you','has','had','been','but','not','all','any','one','out','up','down','into','over','after','about','which','there','then','than','other','some','such','only','und','click','here','read','more','view','page','post','reply','menu','home','results','submit','add','item','privacy','policy','terms','conditions');
		$filtered = apply_filters( 'lee_dev_intent_scanner_blacklist_9447', $default );
		return array_values( array_unique( array_map( 'strtolower', array_filter( array_map( 'trim', is_array( $filtered ) ? $filtered : $default ) ) ) ) );
	}
	public static function normalise_text( string $raw ): string {
		if ( $raw === '' ) return '';
		$text = preg_replace('/<!--\s*\/?wp:[^>]*-->/',' ',$raw);
		$text = strip_shortcodes($text); $text = wp_strip_all_tags($text,true);
		$text = html_entity_decode($text,ENT_QUOTES,get_bloginfo('charset'));
		$text = strtolower($text);
		$text = str_replace(array('&nbsp;',"\xc2\xa0",'-','_'),' ',$text);
		$text = preg_replace('/[.,\/#!$%\^&\*;:{}=`~()?"\'\x{2019}\x{2018}\x{201c}\x{201d}\n\r]/u',' ',$text);
		$text = preg_replace('/\s+/',' ',$text);
		return trim($text);
	}
	public static function get_max_phrase_word_count(): int {
		return (int) apply_filters( 'lee_dev_max_phrase_word_count_5174', 3 );
	}
	public static function extract_candidate_phrases( string $raw, array $blacklist = array() ): array {
		$clean = self::normalise_text($raw); if($clean==='') return array();
		if(empty($blacklist)) $blacklist = self::get_scanner_blacklist();
		$tokens = array_filter(array_map('trim',explode(' ',$clean)));
		$valid = array(); foreach($tokens as $t){ if(strlen($t)<3||in_array($t,$blacklist,true)||!preg_match('/^[a-z][a-z0-9]*$/',$t)) continue; $valid[]=$t; }
		if(empty($valid)) return array();
		$max = max(1,self::get_max_phrase_word_count()); $tc = count($valid); $candidates = array();
		for($n=1;$n<=$max;$n++){ if($n>$tc) break; $last=$tc-$n; for($i=0;$i<=$last;$i++) $candidates[] = implode(' ',array_slice($valid,$i,$n)); }
		return array_values(array_unique($candidates));
	}
	public static function collect_taxonomy_phrases(int $post_id, string $post_type): array {
		$phrases = array();
		if($post_type==='product' && class_exists('WooCommerce')) $taxes = array('product_cat','product_tag');
		elseif($post_type==='post') $taxes = array('category','post_tag');
		else return $phrases;
		$terms = wp_get_object_terms($post_id,$taxes); if(is_wp_error($terms)||empty($terms)) return $phrases;
		foreach($terms as $term){ if(!is_object($term)) continue; if(!empty($term->name)) $phrases[]=(string)$term->name; if(!empty($term->slug)) $phrases[] = str_replace(array('-','_'),' ',(string)$term->slug); }
		return $phrases;
	}
	public static function collect_public_meta_phrases(int $post_id): array {
		$phrases = array(); $all_meta = get_post_meta($post_id); if(empty($all_meta)||!is_array($all_meta)) return $phrases;
		foreach($all_meta as $mk=>$mvs){ if(strpos((string)$mk,'_')===0||!is_array($mvs)) continue; foreach($mvs as $v){ if(is_object($v)) continue; $u=maybe_unserialize($v); if(is_array($u)){ array_walk_recursive($u,function($l)use(&$phrases){ if(is_string($l)||is_numeric($l)) $phrases[]=(string)$l; }); } elseif(is_string($u)||is_numeric($u)) $phrases[]=(string)$u; } }
		return $phrases;
	}
	public static function build_asset_search_corpus(?\WP_Post $post): string {
		if(!$post||empty($post->ID)) return '';
		$pt = !empty($post->post_type)?(string)$post->post_type:'post'; $fragments = array();
		if($pt==='product' && class_exists('WooCommerce')){
			$fragments[]=(string)$post->post_title; $fragments[]=(string)$post->post_content; $fragments[]=(string)$post->post_excerpt;
			if(function_exists('wc_get_product')){ $p=wc_get_product($post->ID); if($p) $fragments[]=(string)$p->get_short_description(); }
			$fragments = array_merge($fragments,self::collect_taxonomy_phrases($post->ID,'product'));
		} elseif($pt==='post'){ $fragments[]=(string)$post->post_title; $fragments=array_merge($fragments,self::collect_taxonomy_phrases($post->ID,'post'));
		} elseif($pt==='page'){ $fragments[]=(string)$post->post_title; $fragments[]=(string)$post->post_content; $fragments=array_merge($fragments,self::collect_public_meta_phrases($post->ID));
		} else { $fragments[]=(string)$post->post_title; }
		$corpus = self::normalise_text(implode(' ',array_filter(array_map('strval',$fragments))));
		return apply_filters('lee_dev_asset_search_corpus_4216',$corpus,$post);
	}
	public static function get_intent_to_site_purpose_map(): array {
		return apply_filters('lee_dev_intent_to_site_purpose_map_5614', array(
			'transactional-intent'=>array('driving_sales'), 'commercial-intent'=>array('driving_sales'),
			'informational-intent'=>array('educating_audiences'), 'engagement-intent'=>array('generating_leads','customer_support'),
			'specialist-intent'=>array(),
		));
	}
	public static function resolve_user_intent_priority(int $user_id): array {
		$admin_priority = method_exists(Propensity::class,'get_global_priority_order') ? Propensity::get_global_priority_order() : array();
		if($user_id<=0 || get_user_meta($user_id,'itp_disable_tracking',true)) return $admin_priority;
		if(!method_exists(Propensity::class,'calculate_group_propensity')) return $admin_priority;
		$scored = array(); foreach(array_keys(self::get_categories()) as $slug){ $s=(int)Propensity::calculate_group_propensity($slug,$user_id); if($s>0) $scored[$slug]=$s; }
		if(empty($scored)) return $admin_priority; arsort($scored,SORT_NUMERIC);
		$map=self::get_intent_to_site_purpose_map(); $waterfall=array();
		foreach(array_keys($scored) as $slug){ if(empty($map[$slug])||!is_array($map[$slug])) continue; foreach($map[$slug] as $p){ if(!in_array($p,$waterfall,true)) $waterfall[]=(string)$p; } }
		foreach($admin_priority as $p){ if(!in_array($p,$waterfall,true)) $waterfall[]=(string)$p; }
		return apply_filters('lee_dev_user_intent_priority_4762',$waterfall,$user_id,$scored,$admin_priority);
	}
	public static function get_manual_label_overrides(int $post_id): array {
		$raw = get_post_meta($post_id,'_itp_manual_label_overrides',true); if(!is_array($raw)) $raw=array();
		$norm = function($list){ if(!is_array($list)) return array(); $out=array(); foreach($list as $e){ $c=self::sanitise_manual_label_phrase($e); if($c!=='') $out[]=$c; } return array_values(array_unique($out)); };
		return array('added'=>isset($raw['added'])?$norm($raw['added']):array(), 'removed'=>isset($raw['removed'])?$norm($raw['removed']):array());
	}
	public static function save_manual_label_overrides(int $post_id, array $overrides): void {
		if($post_id<=0) return; $payload=array('added'=>array(),'removed'=>array());
		if(isset($overrides['added'])&&is_array($overrides['added'])){ foreach($overrides['added'] as $e){ $c=self::sanitise_manual_label_phrase($e); if($c!=='') $payload['added'][]=$c; } $payload['added']=array_values(array_unique($payload['added'])); }
		if(isset($overrides['removed'])&&is_array($overrides['removed'])){ foreach($overrides['removed'] as $e){ $c=self::sanitise_manual_label_phrase($e); if($c!=='') $payload['removed'][]=$c; } $payload['removed']=array_values(array_unique($payload['removed'])); }
		if(empty($payload['added'])&&empty($payload['removed'])){ delete_post_meta($post_id,'_itp_manual_label_overrides'); return; }
		update_post_meta($post_id,'_itp_manual_label_overrides',$payload);
	}
	public static function apply_manual_label_overrides(array $scanner_labels, int $post_id): array {
		$normalised = array(); foreach($scanner_labels as $l){ $c=strtolower(trim((string)$l)); if($c!=='') $normalised[]=$c; } $normalised=array_values(array_unique($normalised));
		$overrides = self::get_manual_label_overrides($post_id);
		if(!empty($overrides['removed'])) $normalised = array_values(array_diff($normalised,$overrides['removed']));
		if(!empty($overrides['added'])){ foreach($overrides['added'] as $m){ if($m!==''&&!in_array($m,$normalised,true)) $normalised[]=$m; } }
		return array_values(array_unique($normalised));
	}
	public static function bucket_candidates(array $candidates): array {
		$buckets = array(); foreach(array_keys(self::get_categories()) as $slug) $buckets[$slug]=array();
		if(!is_array($candidates)||empty($candidates)) return $buckets;
		$cap = (int) apply_filters('lee_dev_intent_dictionary_capacity_5083',50); $seen=array();
		foreach($candidates as $phrase){ $phrase=strtolower(trim((string)$phrase)); if($phrase===''||isset($seen[$phrase])) continue; $seen[$phrase]=true; $slug=self::classify_phrase($phrase); if(!isset($buckets[$slug])) $slug='specialist-intent'; if(count($buckets[$slug])>=$cap) continue; $buckets[$slug][]=$phrase; }
		foreach($buckets as $slug=>$phrases){ $filtered=array(); foreach($phrases as $phrase){ $covered=false; if(strpos($phrase,' ')===false){ foreach($phrases as $cmp){ if($phrase!==$cmp&&strpos($cmp,$phrase)!==false){$covered=true;break;} } } if(!$covered) $filtered[]=$phrase; } $buckets[$slug]=array_values(array_slice($filtered,0,$cap)); }
		return $buckets;
	}
	public static function sanitise_manual_label_phrase(string $raw): string {
		$clean = self::normalise_text($raw); if($clean==='') return '';
		$words = array_values(array_filter(array_map('trim',explode(' ',$clean)))); if(empty($words)) return '';
		$max = max(1,self::get_max_phrase_word_count()); return implode(' ',array_slice($words,0,$max));
	}
}
