<?php
/**
 * Verifies extra-form adapter boundaries using explicit dependency/provider stubs.
 *
 * No native provider submission or cryptographic verification is claimed here.
 * An available unchanged WordPress HTML API additionally checks WS host escaping.
 *
 * @package RktskyAntispamTests
 * @since 0.3.0
 */
namespace Rktsky\Antispam {
	/** Explicit dependency stub; enablement belongs to the registry. @since 0.3.0 */
	final class Settings {}
	/** Explicit guard stub recording transport/content boundaries. @since 0.3.0 */
	final class Guard {
		/** @var array Recorded checks. @since 0.3.0 */
		public array $checks = array();
		/** @var string Synthetic verdict, not cryptographic validation. @since 0.3.0 */
		public string $reason = '';
		/**
		 * Records the adapter's exact guard arguments.
		 * @since 0.3.0
		 * @param string $provider Provider category.
		 * @param string $id Native form identifier.
		 * @param array $posted Technical transport.
		 * @param array $fields Selected public fields.
		 * @return string Synthetic configured reason.
		 */
		public function check(string $provider,string $id,array $posted,array $fields):string {$this->checks[]=array($provider,$id,$posted,$fields);return $this->reason;}
		/**
		 * Provides deterministic neutral retry text.
		 * @since 0.3.0
		 * @return string Public retry message.
		 */
		public function message():string {return 'Please retry <safely>.';}
	}
	/** Explicit escaped-rendering contract stub. @since 0.3.0 */
	final class Fields {
		/**
		 * Makes inside-form scope observable.
		 * @since 0.3.0
		 * @param string $provider Provider category.
		 * @param string $id Native form identifier.
		 * @return string Synthetic controls.
		 */
		public function markup(string $provider,string $id):string {return '<span data-proof="'.$provider.':'.$id.'"></span>';}
		/**
		 * Includes metacharacters to exercise the unchanged native HTML processor.
		 * @since 0.3.0
		 * @param string $provider Provider category.
		 * @param string $id Native form identifier.
		 * @return array Host descriptors requiring native escaping.
		 */
		public function attributes(string $provider,string $id):array {return array('data-rk-antispam-provider'=>$provider,'data-rk-antispam-form'=>$id,'data-rk-antispam-intent'=>'proof"<&','data-rk-antispam-endpoint'=>'/local?a=1&b=2');}
	}
	/** Explicit opt-in retention stub. @since 0.3.0 */
	final class Quarantine {
		/** @var array Synthetic captured selections. @since 0.3.0 */
		public array $captured=array();
		/** @var bool Explicit synthetic opt-in. @since 0.3.0 */
		public bool $active=true;
		/**
		 * Returns the independent retention permission.
		 * @since 0.3.0
		 * @return bool Whether retention is enabled.
		 */
		public function enabled():bool {return $this->active;}
		/**
		 * Records only the selected quarantine contract.
		 * @since 0.3.0
		 * @param string $provider Provider category.
		 * @param string $id Native form ID.
		 * @param string $reason Fixed reason.
		 * @param array $fields Selected content.
		 * @param string $email Declared email only.
		 * @return void
		 */
		public function capture(string $provider,string $id,string $reason,array $fields,string $email=''):void {$this->captured[]=array($provider,$id,$reason,$fields,$email);}
	}
	/** Explicit optional metadata-ledger stub, unused by these adapters. @since 0.3.0 */
	final class Feedback {}
}
namespace {
	if(PHP_SAPI!=='cli'){exit(1);} define('ABSPATH',__DIR__.'/');
	$GLOBALS['extra_hooks']=array();$GLOBALS['extra_assertions']=0;
	/**
	 * Records a native action contract without installing WordPress.
	 * @since 0.3.0
	 * @param string $name Hook name.
	 * @param mixed $callback Callback.
	 * @param int $priority Priority.
	 * @param int $accepted Argument count.
	 * @return void
	 */
	function add_action($name,$callback,$priority=10,$accepted=1):void {$GLOBALS['extra_hooks'][$name]=array($callback,$priority,$accepted);}
	/**
	 * Records a native filter contract.
	 * @since 0.3.0
	 * @param string $name Hook name.
	 * @param mixed $callback Callback.
	 * @param int $priority Priority.
	 * @param int $accepted Argument count.
	 * @return void
	 */
	function add_filter($name,$callback,$priority=10,$accepted=1):void {add_action($name,$callback,$priority,$accepted);}
	/**
	 * Unsplashes only synthetic scalar/array fixture values.
	 * @since 0.3.0
	 * @param mixed $value Synthetic request value.
	 * @return mixed Unslashed fixture value.
	 */
	function wp_unslash($value){return is_array($value)?array_map('wp_unslash',$value):(is_string($value)?stripslashes($value):$value);}
	/**
	 * Supplies the valid-UTF8 fixture boundary.
	 * @since 0.3.0
	 * @param string $value Synthetic valid text.
	 * @param bool $strip Native signature compatibility.
	 * @return string Synthetic text.
	 */
	function wp_check_invalid_utf8($value,$strip=false){return $value;}
	/**
	 * Removes tags for bounded label fixtures.
	 * @since 0.3.0
	 * @param string $value Synthetic text.
	 * @return string Plain fixture text.
	 */
	function wp_strip_all_tags($value){return strip_tags($value);}
	/**
	 * Sanitizes fixture text without claiming WordPress equivalence.
	 * @since 0.3.0
	 * @param string $value Synthetic text.
	 * @return string Plain fixture text.
	 */
	function sanitize_text_field($value){return strip_tags($value);}
	/**
	 * Preserves synthetic textarea newlines.
	 * @since 0.3.0
	 * @param string $value Synthetic textarea.
	 * @return string Plain fixture text.
	 */
	function sanitize_textarea_field($value){return strip_tags($value);}
	/**
	 * Recognizes only valid fixture emails.
	 * @since 0.3.0
	 * @param string $value Synthetic declared email.
	 * @return string|false Valid email or false.
	 */
	function is_email($value){return filter_var($value,FILTER_VALIDATE_EMAIL);}
	/**
	 * Returns an already validated fixture email.
	 * @since 0.3.0
	 * @param string $value Synthetic email.
	 * @return string Fixture email.
	 */
	function sanitize_email($value){return $value;}
	/**
	 * Supplies URI names to the unchanged optional HTML processor.
	 * @since 0.3.0
	 * @return array Native data attributes are deliberately not URI attributes.
	 */
	function wp_kses_uri_attributes(){return array('href','src','action');}
	/** Native declared-field lookup stub. @since 0.3.0 */
	final class FrmField {
		/** @var array Stored synthetic definitions. @since 0.3.0 */
		public static array $definitions=array();
		/**
		 * Returns only the requested native form's definitions.
		 * @since 0.3.0
		 * @param int $id Native form ID.
		 * @param int $limit Maximum definitions.
		 * @param string $embedded Embedded policy.
		 * @param string $repeated Repeating policy.
		 * @return array Synthetic declared fields.
		 */
		public static function get_all_for_form($id,$limit='',$embedded='exclude',$repeated='include'){return self::$definitions[$id]??array();}
	}
	/** Native WS definition lookup stub. @since 0.3.0 */
	final class WS_Form_Common {
		/**
		 * Returns only declared stored-form fields.
		 * @since 0.3.0
		 * @param object $form Native definition.
		 * @param bool $fresh Native cache policy.
		 * @return array Stored synthetic fields.
		 */
		public static function get_fields_from_form($form,$fresh=false){return $form->fields??array();}
	}
	/**
	 * Fails immediately at an adapter contract regression.
	 * @since 0.3.0
	 * @param bool $condition Required boundary.
	 * @param string $message Failure context.
	 * @return void
	 */
	function rktsky_extra_expect(bool $condition,string $message):void {++$GLOBALS['extra_assertions'];if(!$condition){throw new RuntimeException($message);}}
	require dirname(__DIR__).'/src/Integrations/Adapter.php';
	foreach(array('Formidable','Fluent','WsForm') as $class){require dirname(__DIR__).'/src/Integrations/'.$class.'.php';}
	$settings=new \Rktsky\Antispam\Settings();$guard=new \Rktsky\Antispam\Guard();$fields=new \Rktsky\Antispam\Fields();$quarantine=new \Rktsky\Antispam\Quarantine();
	$formidable=new \Rktsky\Antispam\Integrations\Formidable($settings,$guard,$fields,$quarantine);
	$fluent=new \Rktsky\Antispam\Integrations\Fluent($settings,$guard,$fields,$quarantine);
	$ws=new \Rktsky\Antispam\Integrations\WsForm($settings,$guard,$fields,$quarantine);
	foreach(array($formidable,$fluent,$ws) as $adapter){$adapter->register();}
	rktsky_extra_expect(6===count($GLOBALS['extra_hooks']),'Exactly documented render/final-validation hooks must be installed.');
	rktsky_extra_expect(!isset($GLOBALS['extra_hooks']['frm_validate_entry']),'Formidable preliminary validation must not consume final proof.');
	rktsky_extra_expect(4===$GLOBALS['extra_hooks']['fluentform/validation_errors'][2] && 3===$GLOBALS['extra_hooks']['wsf_submit_validate'][2],'Native callback argument contracts must match.');
	ob_start();$formidable->render((object)array('id'=>7));$html=ob_get_clean();rktsky_extra_expect(str_contains($html,'formidable:7'),'Formidable rendering must use stored form ID.');
	ob_start();$fluent->render((object)array('id'=>8));$html=ob_get_clean();rktsky_extra_expect(str_contains($html,'fluentforms:8'),'Fluent rendering must use stored form ID.');
	FrmField::$definitions[7]=array();
	foreach(array(1=>'textarea',2=>'email',3=>'name',4=>'hidden',5=>'password',6=>'file',7=>'credit_card',8=>'text',9=>'checkbox') as $id=>$type){FrmField::$definitions[7][]=(object)array('id'=>$id,'type'=>$type,'name'=>'Field '.$id,'field_options'=>array('admin_only'=>8===$id));}
	$_POST=array('form_id'=>999,'rktsky_antispam_token'=>'proof','item_meta'=>array(1=>'Synthetic body',2=>'fixture@example.invalid',3=>array('first'=>'First','last'=>'Last','password'=>'secret'),4=>'hidden-secret',5=>'password-secret',6=>'file-secret',7=>'payment-secret',8=>'admin-secret',9=>array(0=>'Choice','private'=>'extra-secret'),99=>'unknown-secret'));
	$guard->reason='keyword';$result=$formidable->validate('',(object)array('id'=>7));$check=end($guard->checks);$capture=end($quarantine->captured);
	rktsky_extra_expect(isset($result['rktsky_antispam']) && '7'===$check[1],'Final Formidable rejection must be visible and bound to native form.');
	rktsky_extra_expect(4===count($check[3]) && !str_contains(json_encode($check[3]),'secret'),'Formidable selected values must exclude sensitive/unknown/compound extra parts.');
	rktsky_extra_expect('fixture@example.invalid'===$capture[4],'Only declared email may populate the quarantine subject index.');
	$count=count($guard->checks);$prior=array('native'=>'Earlier error');rktsky_extra_expect($prior===$formidable->validate($prior,(object)array('id'=>7)) && count($guard->checks)===$count,'Existing Formidable validation errors must remain intact.');
	$guard->reason='';rktsky_extra_expect(''===$formidable->validate('',(object)array('id'=>7)),'Successful Formidable proof must preserve the native empty result.');
	$trap='rktsky_as_'.str_repeat('a',20);$transport=http_build_query(array('rktsky_antispam_token'=>'exact.proof','your-message'=>'not-technical',$trap=>''));
	$definitions=array();foreach(array('message'=>'textarea','email'=>'input_email','password'=>'input_password','hidden'=>'input_hidden','upload'=>'input_file','payment'=>'payment_component') as $name=>$element){$definitions[$name]=array('element'=>$element,'raw'=>array('element'=>$element,'settings'=>array('label'=>$name)));}
	$values=array('message'=>'Synthetic fluent body','email'=>'fixture@example.invalid','password'=>'password-secret','hidden'=>'hidden-secret','upload'=>'file-secret','payment'=>'payment-secret','unknown'=>'unknown-secret');
	$_POST=array('data'=>$transport);$guard->reason='honeypot';$result=$fluent->validate(array(),$values,(object)array('id'=>8),$definitions);$check=end($guard->checks);
	rktsky_extra_expect(isset($result['rktsky_antispam'][0]) && array('rktsky_antispam_token'=>'exact.proof',$trap=>'')===$check[2],'Fluent must decode only exact technical controls from native serialized data.');
	rktsky_extra_expect(2===count($check[3]) && !str_contains(json_encode($check[3]),'secret'),'Fluent content must retain declared text/email and exclude sensitive types.');
	rktsky_extra_expect(!isset($values['rktsky_antispam_token']),'Fluent technical proof must not be inserted into native persisted formData.');
	$count=count($guard->checks);rktsky_extra_expect($prior===$fluent->validate($prior,$values,(object)array('id'=>8),$definitions) && count($guard->checks)===$count,'Fluent native errors must not be reversed.');
	foreach(array($transport.'&rktsky_antispam_token=duplicate',$transport.'&rktsky_antispam_token%5B%5D=ambiguous',str_repeat('x',1048577)) as $invalid){$_POST=array('data'=>$invalid);$fluent->validate(array(),array(),(object)array('id'=>8),array());rktsky_extra_expect(array()===end($guard->checks)[2],'Malformed, duplicate and oversized Fluent proof transports must fail closed.');}
	$bad=$definitions;$bad['message']['raw']['element']='input_hidden';$_POST=array('data'=>$transport);$fluent->validate(array(),$values,(object)array('id'=>8),$bad);rktsky_extra_expect(1===count(end($guard->checks)[3]),'Fluent parsed/raw type mismatches must not enter rules or quarantine.');
	$native_fields=array();foreach(array(1=>'textarea',2=>'email',3=>'hidden',4=>'password',5=>'file',6=>'price',7=>'text') as $id=>$type){$native_fields[]=(object)array('id'=>$id,'type'=>$type,'label'=>'Field '.$id);}
	$submission=(object)array('form_id'=>9,'form_object'=>(object)array('id'=>9,'fields'=>$native_fields),'meta'=>array('field_1'=>array('id'=>1,'type'=>'textarea','value'=>'WS body'),'field_2'=>array('id'=>2,'type'=>'email','value'=>'fixture@example.invalid'),'field_3'=>array('id'=>3,'type'=>'hidden','value'=>'hidden-secret'),'field_4'=>array('id'=>4,'type'=>'password','value'=>'password-secret'),'field_5'=>array('id'=>5,'type'=>'file','value'=>'file-secret'),'field_6'=>array('id'=>6,'type'=>'price','value'=>'payment-secret'),'field_7'=>array('id'=>7,'type'=>'hidden','value'=>'mismatch-secret'),'field_1_0'=>array('id'=>1,'type'=>'textarea','value'=>'fallback-secret','db_ignore'=>true),'field_999'=>array('id'=>999,'type'=>'text','value'=>'unknown-secret')),'meta_protected'=>array('field_10'=>array('id'=>10,'type'=>'text','value'=>'protected-secret')));
	$_POST=array('rktsky_antispam_token'=>'ws-proof',$trap=>'');$result=$ws->validate(array(),'submit',$submission);$check=end($guard->checks);
	rktsky_extra_expect('danger'===$result[0]['type'] && false===$result[0]['form_hide'] && 'message'===$result[0]['action'],'WS rejection must use visible native actions while keeping the form available.');
	rktsky_extra_expect(2===count($check[3]) && !str_contains(json_encode($check[3]),'secret'),'WS metadata must match declared safe IDs/types and exclude protected/fallback/sensitive data.');
	$before=count($guard->checks);$earlier=array(array('action'=>'native'));rktsky_extra_expect($earlier===$ws->validate($earlier,'submit',$submission) && count($guard->checks)===$before,'WS earlier validation actions must be preserved.');
	$guard->reason='';rktsky_extra_expect(array()===$ws->validate(array(),'save',$submission),'WS save callbacks must also require a guard check and preserve native success.');
	$quarantine->active=false;$guard->reason='missing';$count=count($quarantine->captured);$ws->validate(array(),'submit',$submission);rktsky_extra_expect(count($quarantine->captured)===$count,'Shared retention opt-out must remain authoritative.');
	$wp_root=getenv('WP_ANTISPAM_WP_ROOT')?:'/tmp/rktsky-antispam-runtime/site/wordpress';
	if(is_file($wp_root.'/wp-includes/html-api/class-wp-html-tag-processor.php')){
		if(is_file($wp_root.'/wp-includes/utf8.php')){require_once $wp_root.'/wp-includes/utf8.php';}
		foreach(array('span','attribute-token','text-replacement','decoder','tag-processor') as $name){require_once $wp_root.'/wp-includes/html-api/class-wp-html-'.$name.'.php';}
		$html=$ws->render('<div><form class="wsf-form" data-id="9"></form><form class="other" data-id="10"></form><form class="wsf-form" data-id="bad"></form></div>');
		rktsky_extra_expect(1===substr_count($html,'data-rk-antispam-provider='),'Native HTML processing must decorate only actual WS forms with valid native IDs.');
		rktsky_extra_expect(str_contains($html,'proof&quot;&lt;&amp;') && str_contains($html,'/local?a=1&amp;b=2'),'The original WordPress HTML API must escape host descriptor values.');
	}
	echo 'Extra-form adapter contracts: '.$GLOBALS['extra_assertions']." assertions passed.\n";
}
