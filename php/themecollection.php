<?php
namespace pickles2\libs\themeEditor;
class themecollection{

	/** $main */
	private $main;

	/**
	 * Constructor
	 */
	public function __construct($main){
		$this->main = $main;
	}

	/**
	 * テーマコレクションをリスト化
	 */
	public function get_list(){
		$themeCollection = array();
		$px2all = $this->main->px2all();
		$ls = $this->main->fs()->ls($px2all->realpath_theme_collection_dir);

		$realpathDefaultThumb = 'data:image/png;base64,'.base64_encode( file_get_contents( __DIR__.'/../resources/no-image.png' ) );

		foreach( $ls as $idx=>$basename ){
			if( !is_dir( $px2all->realpath_theme_collection_dir . $basename . '/' ) ){
				continue;
			}
			$themeInfo = array();
			$themeInfo['id'] = $basename;
			$themeInfo['name'] = $basename;
			$themeInfo['thumb'] = $realpathDefaultThumb;

			if( is_file( $px2all->realpath_theme_collection_dir . $basename . '/thumb.png' ) ){
				$themeInfo['thumb'] = 'data:image/png;base64,'. base64_encode( file_get_contents( $px2all->realpath_theme_collection_dir . $basename . '/thumb.png' ) );
			}

			array_push( $themeCollection, $themeInfo );
		}

		return $themeCollection;
	} // get_list();


	/**
	 * テーマの情報を取得する
	 */
	public function get_theme_info( $theme_id ){
		$rtn = array();
		$px2all = $this->main->px2all();
		$realpath_theme_root = $px2all->realpath_theme_collection_dir.$theme_id;

		$rtn['layouts'] = array();
		$ls = $this->main->fs()->ls($realpath_theme_root.'/');
		foreach( $ls as $layoutId ){
			if( !is_file( $realpath_theme_root.'/'.$layoutId ) ){
				continue;
			}
			if( !preg_match('/\.html$/', $layoutId) ){
				continue;
			}
			$layoutId = preg_replace('/\.[a-zA-Z0-9]+$/i', '', $layoutId);
			$editMode = 'html';
			if( is_file( $realpath_theme_root.'/'.$layoutId.'/guieditor.ignore/'.$layoutId.'/data/data.json' ) ){
				$editMode = 'html.gui';
			}

			array_push( $rtn['layouts'], array(
				'id' => $layoutId,
				'editMode' => $editMode,
			) );
		}

		// READMEを取得
		$rtn['readme'] = '';
		if( is_file( $realpath_theme_root.'/'.'/README.md' ) ){
			$rtn['readme'] = file_get_contents( $realpath_theme_root.'/'.'/README.md' );
			$rtn['readme'] = \Michelf\MarkdownExtra::defaultTransform($rtn['readme']);
		}else if( is_file( $realpath_theme_root.'/'.'/README.html' ) ){
			$rtn['readme'] = file_get_contents( $realpath_theme_root.'/'.'/README.html' );
		}

		// サムネイルを取得
		$rtn['thumb'] = '';
		$realpathImage = __DIR__.'/../resources/no-image.png';
		if( is_file( $realpath_theme_root.'/thumb.png' ) ){
			$realpathImage = $realpath_theme_root.'/thumb.png';
		}
		$rtn['thumb'] = 'data:image/png;base64,'.base64_encode(file_get_contents( $realpathImage ));

		return $rtn;
	}

	/**
	 * 新規テーマを追加する
	 */
	public function add_new_theme( $new_theme_id, $options = array() ){
		$rtn = array(
			'result' => null,
			'message' => '',
		);
		$px2all = $this->main->px2all();
		$realpath_theme_root = $px2all->realpath_theme_collection_dir.urlencode($new_theme_id).'/';
		$importFrom = $options['importFrom'];

		if( is_dir( $realpath_theme_root ) ){
			$rtn = array(
				'result' => false,
				'message' => 'テーマID '.$new_theme_id.' は、すでに存在します。',
			);
			return $rtn;
		}

		// フォルダ生成
		$this->main->fs()->mkdir_r( $realpath_theme_root );
		if( !$importFrom ){
			$this->main->fs()->save_file( $realpath_theme_root.'/default.html', '' );
			$this->main->fs()->save_file( $realpath_theme_root.'/plain.html', '' );
			$this->main->fs()->save_file( $realpath_theme_root.'/naked.html', '' );
			$this->main->fs()->save_file( $realpath_theme_root.'/popup.html', '' );
			$this->main->fs()->save_file( $realpath_theme_root.'/top.html', '' );

			$rtn = array(
				'result' => true,
				'message' => 'OK',
			);

		}else{
			preg_match('/^(themeCollection|themePlugin)\:([\S]+)$/', $importFrom, $matched);

			$fromDiv = $matched[1];
			$fromId = $matched[2];
			if( $fromDiv == 'themeCollection' ){
				$result = $this->main->fs()->copy_r(
					$px2all->realpath_theme_collection_dir.$fromId,
					$realpath_theme_root
				);
			}else if($fromDiv == 'themePlugin'){
				$themePluginList = $px2all->packages->package_list->themes;
				$pluginInfo = $themePluginList->{$fromId};
				$result = $this->main->fs()->copy_r(
					$pluginInfo->path,
					$realpath_theme_root
				);
			}
			if( $result ){
				$rtn = array(
					'result' => true,
					'message' => 'OK',
				);
			}else{
				$rtn = array(
					'result' => false,
					'message' => '新しいテーマの生成に失敗しました。',
				);
			}
		}

		return $rtn;
	}

	/**
	 * テーマを改名する
	 */
	public function rename_theme( $new_theme_id, $rename_from ){
		$rtn = array(
			'result' => null,
			'message' => '',
		);
		$px2all = $this->main->px2all();
		$realpath_theme_root = $px2all->realpath_theme_collection_dir.urlencode($new_theme_id).'/';

		if( is_dir( $realpath_theme_root ) ){
			$rtn = array(
				'result' => false,
				'message' => 'テーマID '.$new_theme_id.' は、すでに存在します。',
			);
			return $rtn;
		}

		$result = $this->main->fs()->rename( $px2all->realpath_theme_collection_dir.urlencode($rename_from).'/', $realpath_theme_root );

		if( !$result ){
			$rtn = array(
				'result' => false,
				'message' => 'テーマディレクトリの改名に失敗しました。',
			);
			return $rtn;
		}

		$rtn = array(
			'result' => true,
			'message' => 'OK',
		);
		return $rtn;
	}

}
