<?php

/**
 * Class TK_GVote
 */
class TK_GVote
{

    /**
     * WordPress Database Access Abstraction Object
     * @var
     */
    protected $wpdb;
    /**
     * @var integer|null
     */
    protected $vote_id;

    /**
     * @var integer|null
     */
    protected $project_id;

    /**
     * @var null $post_id
     */
    public function __construct($post_id = null)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
		$this->wpdb->enable_nulls = true;
        


        if (isset($post_id)) {
            $res = get_post($post_id);

            if (is_object($res)) {
                

                $this->project_id = $res->ID;
                $this->vote_id = $this->wpdb->get_var($this->wpdb->prepare("SELECT id 
													 FROM {$this->wpdb->prefix}tkgp_votes 
													 WHERE post_id = %d",
                    $this->project_id)
                );
            }
        }
    }

    /**
     * @todo этот метод лишний в классе
     * @param integer $post_id
     * @return bool
     */
    public static function exists($post_id)
    {
        global $wpdb;
        if (isset($post_id)) {
            
            $res = $wpdb->get_var(
                $wpdb->prepare("SELECT count(id) FROM {$wpdb->prefix}tkgp_votes  WHERE post_id = %d;", array($post_id))
            );
            if ($res) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array
     */
    public static function getVotesFields()
    {
        return array(
            array(
                'label' => _x('Enable Vote', 'Project Settings', 'tkgp'),
                'desc' => _x('Enable/Disable vote for this project.', 'Project Settings', 'tkgp'),
                'id' => 'tkgp_vote_enabled',
                'type' => 'radio',
                'options' => array(
                    array(
                        'label' => _x('Enable', 'Project Settings', 'tkgp'),
                        'value' => '1'
                    ),
                    array(
                        'label' => _x('Disable', 'Project Settings Type', 'tkgp'),
                        'value' => '0'
                    )
                )
            ),
            array(
                'label' => _x('Vote for approval', 'Project Settings', 'tkgp'),
                'desc' => _x('Number of votes in which the project is considered approved.', 'Project Settings',
                    'tkgp'),
                'id' => 'tkgp_vote_target_votes',
                'type' => 'number',
                'value' => 100,
                'options' => array(
                    'min' => '1',
                    'step' => 1
                )
            ),
            array(
                'label' => _x('Start date', 'Project Settings', 'tkgp'),
                'desc' => _x('The date of commencement of voting.', 'Project Settings', 'tkgp'),
                'id' => 'tkgp_vote_start_date',
                'type' => 'date',
                'options' => array('required')
            ),
            array(
                'label' => _x('End date', 'Project Settings', 'tkgp'),
                'desc' => _x('The data of the end of voting.', 'Project Settings', 'tkgp'),
                'id' => 'tkgp_vote_end_date',
                'type' => 'date'
            ),
            array(
                'label' => _x('Allow re-vote', 'Project Settings', 'tkgp'),
                'desc' => _x('Users can reset their vote and vote again, or not to vote :)', 'Project Settings', 'tkgp'),
                'id' => 'tkgp_vote_allow_revote',
                'type' => 'checkbox',
                'exclude' => 1,
                'value' => 1
            ),
            array(
                'label' => _x('Reset voting results', 'Project Settings', 'tkgp'),
                'desc' => _x('Reset voting results.', 'Project Settings', 'tkgp'),
                'id' => 'tkgp_vote_reset',
                'type' => 'checkbox',
                'exclude' => 1,
                'value' => 1
                
            )
        );
    }

    /**
     * @param integer $user_id
     * @return bool
     */
    public function userCanVote($user_id)
    {
        if (!empty($this->vote_id) && !empty($user_id)) {
            

            $res = $this->wpdb->get_var($this->wpdb->prepare("SELECT count(uv.id)
										FROM {$this->wpdb->prefix}tkgp_usersvotes uv, {$this->wpdb->prefix}tkgp_votes vv
										WHERE vv.id = uv.vote_id
										AND vv.enabled = 1
										AND uv.vote_id = %d
										AND uv.user_id = %d;",
                $this->vote_id, $user_id)
            );
            if (empty($res)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $users
     * @return mixed[]
     * @internal param mixed[] $arg
     */
    public function getUsersVotes($users)
    {
        if (isset($this->vote_id) && !empty($users)) {
            

            $query = $this->wpdb->prepare("SELECT * 
										FROM {$this->wpdb->prefix}tkgp_usersvotes 
										WHERE vote_id = %d 
										AND user_id IN (". implode(",", $users).")",
                $this->vote_id);

            return $this->wpdb->get_results($query, ARRAY_A);
        }
        return array();
    }

    /**
     * @param bool $include_percents
     * @return mixed[]
     */
    public function getVoteState($include_percents = false)
    {
        if (isset($this->vote_id)) {
            

            if ($this->variantExists()) {
                $query = $this->wpdb->prepare("SELECT uv.variant_id AS id, vv.variant AS variant, count(uv.id) AS cnt 
											FROM {$this->wpdb->prefix}tkgp_usersvotes uv, {$this->wpdb->prefix}tkgp_votevariant vv
											WHERE vv.vote_id = uv.vote_id
											AND uv.vote_id = %d
											GROUP BY uv.variant_id, vv.variant"
                    , $this->vote_id);
            } else {
                $query = $this->wpdb->prepare("SELECT uv.variant_id AS id, count(uv.id) AS cnt 
											FROM {$this->wpdb->prefix}tkgp_usersvotes uv
											WHERE uv.vote_id = %d
											GROUP BY uv.variant_id
											ORDER BY uv.variant_id DESC"
                    , $this->vote_id);
            }

            return $this->wpdb->get_results($query, ARRAY_A);
        }
        return array();
    }

    /**
     * @return bool
     */
    public function variantExists()
    {
        if (isset($this->vote_id)) {
            

            $res = $this->wpdb->get_var($this->wpdb->prepare("SELECT count(id) 
												FROM {$this->wpdb->prefix}tkgp_votevariant 
												WHERE vote_id = %d",
                $this->vote_id)
            );
            if (intval($res)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param mixed[] $arg
     * @return mixed[]|null
     */
    public function getVoteSettings($arg = array())
    {
        if (is_array($arg) && isset($this->vote_id)) {
            $columns = '*';

            foreach ($arg as $cur) {
                switch ($cur) {
                    case 'id':
                    case 'ID':
                    case 'post_id':
                    case 'enabled':
                    case 'start_date':
                    case 'end_date':
                    case 'target_votes':
					case 'allow_revote':
                        if ($columns == '*') {
                            $columns = $cur;
                        } else {
                            $columns = $columns . ', ' . $columns;
                        }
                        break;

                    default:
                        continue;
                }
            }



            return $this->wpdb->get_row($this->wpdb->prepare("SELECT {$columns} FROM {$this->wpdb->prefix}tkgp_votes WHERE id = %d;",
                $this->vote_id), ARRAY_A);

        }
        return array();
    }

	/**
	 * @param array $arg
	 * @return bool
	 */
	public function updateVoteSettings($arg) {
		if(isset($this->vote_id) && isset($arg)) {
			$data = array();
			$format = array();
			foreach ($arg as $key => $val) {
				$cur_format = '';
				switch ($key) {
					case 'enabled':
					case 'target_votes':
					case 'allow_revote':
						$cur_format = '%d';
					case 'start_date':
					case 'end_date':
						if(empty($cur_format)) {
							$cur_format = '%s';
						}
						
						$data[$key] = $val;
						$format[] = $cur_format;
						break;
					
					default:
						continue;
				}
			}
			
			$res = $this->wpdb->update($this->wpdb->prefix . 'tkgp_votes', $data, 
								array('id' => $this->vote_id),
								$format,
								array('%d'));
		}
		return false;
	}

    /**
     * @param string $key
     * @param mixed $val
     * @return bool
     */
    public function setVoteSetting($key, $val)
    {
        if (isset($this->vote_id)) {
            $format = array();

            switch ($key) {
                case 'start_date':
                case 'end_date':
                    $format[] = '%s';
                    break;

                default:
                    $format[] = '%d';
                    break;
            }
			
            $res = $this->wpdb->update($this->wpdb->prefix . 'tkgp_votes',
                array($key => $val),
                array('id' => $this->vote_id),
                $format,

                array('%d'));
            if ($res !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param int $user_id
     * @param int $variant_id
     * @return bool
     */
    public function addUserVote($user_id, $variant_id)
    {
        if (isset($user_id) && isset($variant_id) && $this->userCanVote($user_id)) {
            

            $data = array(
                'vote_id' => $this->vote_id,
                'user_id' => $user_id,
                'variant_id' => $variant_id
            );

            $format = array(
                '%d',
                '%d',
                '%d'
            );

            $res = $this->wpdb->insert($this->wpdb->prefix . 'tkgp_usersvotes', $data, $format);

            if ($this->wpdb->insert_id) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public function deleteUserVote($user_id)
    {
        if (isset($this->vote_id) && isset($user_id)) {
            

            $res = $this->wpdb->query($this->wpdb->prepare("
					DELETE FROM {$this->wpdb->prefix}tkgp_usersvotes 
					WHERE vote_id = %d 
					AND user_id = %d;
					",
                $this->vote_id, $user_id)
            );

            if (intval($res) == 0) {
                return true;
            }
        }
        return false;
    }

	/**
	 * Reset all votes
	 */
	public function resetVote() {
		if (isset($this->vote_id)) {
            

            $res = $this->wpdb->query($this->wpdb->prepare("
					DELETE FROM {$this->wpdb->prefix}tkgp_usersvotes 
					WHERE vote_id = %d
					",
                $this->vote_id)
            );
        }
	}

    /**
     * @param mixed[] $arg
     * @return bool
     */
    public function createVote($arg = array())
    {
        if (isset($this->project_id) && !$this->voteExists()) {
            $data = array(
                'post_id' => $this->project_id,
                'enabled' => 1,
                'start_date' => $this->val($arg['tkgp_start_date'], date('YmdHis')),
                'end_date' => $this->val($arg['tkgp_end_date'], null),
                'target_votes' => $this->val($arg['tkgp_target_votes'], 100)
            );

            $format = array(
                '%d',
                '%d',
                '%s',
                '%s',
                '%d'
            );

            $this->wpdb->insert($this->wpdb->prefix . 'tkgp_votes', $data, $format);
            if ($this->wpdb->insert_id) {
                $this->vote_id = $this->wpdb->insert_id;
                return true;
            }
        }
        return false;
    }

    /**
     * @param mixed[] $arg
     * @return bool
     */
    public function updateVote($arg)
    {
        return false;
    }

    /**
     * @return bool
     */
    public function voteExists()
    {
        return self::exists($this->project_id);
    }

    /**
     * @return mixed[]
     */
    public function getVoteVariants()
    {
        if (isset($this->vote_id)) {


            $query = $this->wpdb->prepare("SELECT variant_id, variant, approval_flag 
										FROM {$this->wpdb->prefix}tkgp_votevariant
										WHERE vote_id = %d
										ORDER BY approval_flag DESC
										",
                $this->vote_id);

            return $this->wpdb->get_results($query, ARRAY_A);
        }

        return array();
    }

    /**
     * @param false $show_vote_button
	 * @param false $short
	 * @return string
     */
    public function getResultVoteHtml($show_vote_button = false, $short = false)
    {
        $form = '';

        if (isset($this->vote_id)) {
        	$settings = $this->getVoteSettings(array('target_votes','enabled','allow_revote','start_date','end_date'));
            $target_votes = floatval($settings['target_votes']);
            $votes = $this->getVoteState();
            $form .= '<div id="tkgp_vote_result">
            	<div><b>'. _x('Voting status', 'Project Vote', 'tkgp') .'</b></div>';

            if ($this->variantExists()) {
				$form .= $this->getVoteButtonHtml(true);
            } else {
                $approval = 100.0 * floatval(isset($votes[0]) ? $votes[0]['cnt'] : 0) / $target_votes;
                $reproval = 100.0 * floatval(isset($votes[1]) ? $votes[1]['cnt'] : 0) / $target_votes;

                $form .= '<table>
                	<tr><th>'. _x('Progress of the approval', 'Project Vote', 'tkgp') .'</th>
                	<td>
                		<div id="tkgp_approval_status">';
                $form .= '<div id="tkgp_approval" style="float: left; width: ' . $approval . '%; height: 100%; background: #FF0000;"></div>';
                	/*Код на будущее, когда будет реализация голосов против Проекта*/
					/*<div id="tkgp_reproval" style="float: left; width: ' . $reproval . '%; height: 100%; background: #EEE;"></div>';*/
                $form .= '</td>
                </tr>';
				if(!$short) {
					$form .= '<tr><th>'. _x('Supported', 'Project Vote', 'tkgp') . '</th><td>' . intval(isset($votes[0]) ? $votes[0]['cnt'] : 0) .'</td></tr>';
					$form .= '<tr><th>'. _x('Not Supported', 'Project Vote', 'tkgp') . '</th><td>' . intval(isset($votes[0]) ? $votes[1]['cnt'] : 0) .'</td></tr>';
					if($show_vote_button) {
						$form .= '<tr><td colspan="2">' . $this->getVoteButtonHtml() . '</td></tr>';
					}
				}
            	$form .= '</table>';
			}

            $form .= '</div>';
        }
        return $form;
    }
	
	/**
	 * @param bool $variant_exists[optional]
	 * @return string
	 */
	protected function getVoteButtonHtml($variant_exists = false) {
		$html_code = '<div id="tkgp_vote_buttons">
							<input type="hidden" name="tkgp_vote_nonce" value="'. wp_create_nonce('tkgp_user_vote') .'" />
							<input type="hidden" name="tkgp_vote_id" value="'. $this->vote_id .'">
							<input type="hidden" name="tkgp_post_id" value="'. $this->project_id .'">';
		
		if($variant_exists) {
			$vars = $this->getVoteVariants();
			$html_code .= '<ul class="tkgp_variants">';
			
			foreach ($vars as $var) {
				$html_code .= '<li><input type="radio" name="user_vote" value="'. $var['variant_id'] .'" require/>'. $var['variant'] .'</li>';
			}
			
			$html_code .= '</ul>
			<div class="tkgp_button"><a>'. _x('Vote', 'Project Vote', 'tkgp') .'</a></div>';
			
		} else {
			$html_code .= '<div>
							<div class="tkgp_button">
							<a>'. _x('I support','Project Vote', 'tkgp') .'</a>
							<input type="hidden" name="user_vote" value="-1"/>
						  	</div>
						  </div>
						  <div>
						  	<div class="tkgp_button">
						  		<a>'. _x('I do\'t support','Project Vote', 'tkgp') .'</a>
						  		<input type="hidden" name="user_vote" value="-2"/>
						  	</div>
						  </div>';
		}
		
		$html_code .= '</div>';
		
		return $html_code;
	}
	
    /**
     * @param mixed[] $arg
     * @return bool
     */
    protected function createVoteVariants($arg)
    {
        if ($this->voteExists() && is_array($arg) && array_key_exists('tkgp_var_cnt', $arg)) {
            

            for ($i = 1; $i <= intval($arg['tkgp_var_cnt']); $i++) {
                if (!isset($arg['tkgp_var_id' . $i]) || !isset($arg['tkgp_var' . $i])) {
                    continue;
                }

                $data = array(
                    'vote_id' => $this->vote_id,
                    'variant_id' => $i,
                    'variant' => $arg['tkgp_var' . $i],
                    'approval_flab' => intval($arg['tkgp_appr_id']) == $i ? 1 : 0
                );

                $format = array(
                    '%d',
                    '%d',
                    '%s',
                    '%d'
                );


                $this->wpdb->insert($this->wpdb->prefix . 'tkgp_votevariant', $data, $format);
            }

            if ($this->wpdb->insert_id) {
                return true;
            }
        }
        return false;
    }

	/**
	 * @return null|int
	 */
	public function getPostId() {
		return $this->project_id;
	}
	
	/**
	 * @return null|int
	 */
	public function getVoteId() {
		return $this->vote_id;
	}
	
    /**
     * @param mixed|mixed[] $val
     * @param mixed|mixed[] $default
     * @return mixed|mixed[]
     */
    protected function val($val, $default)
    {
        return isset($val) ? $val : $default;
    }
}

;
?>