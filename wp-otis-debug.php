<?php

class WpOtisDebug {

    public $message = "";

    /**
     * Hook onto all of the actions and filters needed by the plugin.
     */
    protected function __construct() {
        add_action( 'init',                               array( $this, 'action_handle_post' ) );
        add_action( 'admin_menu',                         array( $this, 'action_admin_menu' ) );
    }

    public function action_handle_post( ) {
        if ( isset( $_POST ) ) {

            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( "Unauthorized User" );
            }

            if ( isset( $_POST['term_removal']['termids'] ) ) {
                foreach ($_POST['term_removal']['termids'] as $term_id) {

                    if (is_numeric($term_id)) {

                        $term = get_term($term_id);

                        if ($term) {
                            if (strpos($term->name, '| ') !== false) {
                                if (wp_delete_term($term_id,$term->taxonomy)) {
                                    $this->message .= "<strong>Term deleted</strong> - ".$term->name. " (".$term_id.")<br>";
                                } else {
                                    $this->message .= "<strong>Term deletion failed</strong> - ".$term->name. " (".$term->term_id.")<br>";
                                }
                            } else {
                                $this->message .= "<strong>Term NOT deleted</strong> (no match for string '| ') - ".$term->name. " (".$term->term_id.")";
                            }
                        } else {
                            $this->message .= "<strong>Term not found</strong> - ".$term_id;
                        }
                    }
                }
            }

            if ( isset( $_POST['post_reimport'] ) ) {

                $reimport_count = isset( $_POST['post_reimport']['poi_count'] ) ? $_POST['post_reimport']['poi_count'] : 10;
                $reimport_ids = $_POST['post_reimport']['postids'];

                $otis     = new Otis();
                $logger   = new Otis_Logger_Simple();
                $importer = new Otis_Importer( $otis, $logger );

                $this->message .= "POIs updated: ";

                for ($i = 0; $i < $reimport_count; $i++) {

                    $post_reimport = $reimport_ids[$i];

                    if ( $post_reimport ) {

                        try {
                            $importer->import( [ 'poi', get_post_meta($post_reimport,'uuid', true) ], [] );
                            $this->message .= " {$post_reimport},";
                            sleep(10);
                        } catch ( Exception $e ) {
                            $logger->log("OTIS debugger couldn't reimport POI #{$post_reimport}: {$e}");
                        }
                    }
                }
            }
        }
    }

    /**
     * Adds tool page to the admin menu.
     */
    public function action_admin_menu() {
        add_management_page( 'WP OTIS Debug', 'WP OTIS Debug', 'manage_options', 'wp_otis_debug', array( $this, 'admin_manage_page' ) );
    }

    /**
     * Displays the manage page for the plugin.
     */
    public function admin_manage_page() {  ?>

        <?php echo $this->admin_styles; ?>

        <script>

            loadfunc = function() {

                var loading = document.getElementsByClassName("loading");
                for (var i = 0; i < loading.length; i++) {
                    loading[i].classList.add("hidden");
                }

                var classname = document.getElementsByClassName("hideshow-pois");
                var refresher = document.getElementsByClassName("refresh");

                if (refresher[0])
                    refresher[0].addEventListener("click", function() {location.reload()}, false);

                toggleDisplayClass = function(e) {
                    e.preventDefault();
                    this.parentNode.classList.toggle("active");
                };

                for (var i = 0; i < classname.length; i++) {
                    classname[i].classList.add("show");
                    classname[i].addEventListener("click", toggleDisplayClass, false);
                }
            };

            document.addEventListener("DOMContentLoaded", loadfunc, false);

        </script>

        <div class="otis-debugger-wrapper">

            <h1>WP OTIS debug</h1>

            <h2 class="loading" style="margin-bottom: 0">Loading...</h2>
            <p class="loading">Please wait</p>

            <?php

            $questionable_term_list = [
                'type',
                'amenities',
                'glocats',
                'bike_friendly_business_amenities',
                'ava'
            ];

            $questionable_term_poi_counts = [];

            foreach ($questionable_term_list as $qt) {
                $questionable_term_poi_counts[$qt] = 0;
                $questionable_terms[$qt] = $this->get_questionable_term($qt);
            }

            if (!empty($this->message)) {
                echo "<p>".rtrim($this->message,",")."</p>";
            }

            ?>

            <?php if (empty($_POST)): ?>


                <p>POI taxonomies checked for pipe-or-comma import errors: <?php echo implode(', ', $questionable_term_list) ?>.</p>

                <?php foreach($questionable_terms as $term_slug => $term ): ?>

                    <?php if ($term) { ?>
                        <h3><?php echo $term_slug; ?></h3>
                        <p>
                            Questionable POI <?php echo $term_slug; ?> detected (<?php echo sizeOf($questionable_terms[$term_slug]); ?>).
                            <a href="" class="hideshow-pois">Toggle term display</a></p>
                        <div class="wrap">
                            <table class="widefat striped">
                                <tr><th>ID</th><th>Name</th><th>POI Count</th><th>Delete?</th></tr>

                                <?php

                                $potential_reimport_array = [];
                                $term_removal_array = [];

                                foreach($term as $qtype):

                                    if ($term_slug == 'amenities' || $term_slug == 'bike_friendly_business_amenities') {
                                        $query = new WP_Query(array(
                                            'posts_per_page' => -1,
                                            'post_type'      => 'poi',
                                            'order'          => 'ASC',
                                            'post_status'    => 'publish',
                                            'meta_query' => array (array (
                                                'key' => $term_slug,
                                                'value' => $qtype['id'],
                                                'compare' => 'LIKE'
                                            ))
                                        ));
                                        $qtype['count'] = $query->post_count;
                                    } else {
                                        $query = new WP_Query(array(
                                            'posts_per_page' => -1,
                                            'post_type'      => 'poi',
                                            'order'          => 'ASC',
                                            'post_status'    => 'publish',
                                            'tax_query' => array(
                                                array(
                                                    'taxonomy' => "type",
                                                    'field' => 'term_id',
                                                    'terms' => $qtype['id'],
                                                    'include_children' => false
                                                )
                                            )
                                        ));
                                    }

                                    $questionable_term_poi_counts[$term_slug] = $questionable_term_poi_counts[$term_slug] + $qtype['count'];

                                    echo "<tr>";
                                    echo "<td>". $qtype['id'] ."</td>";
                                    echo "<td>". $qtype['name'] ."</td>";
                                    echo "<td><a href='/wp/wp-admin/edit.php?type=".$qtype['slug']."&post_type=poi'>".$qtype['count']."</a></td>";

                                    if ( $query->have_posts() ) {

                                        echo "<td></td>";
                                        echo "</tr>";

                                        echo '<tr><td colspan="4">Live POIs:';

                                        while ( $query->have_posts() ) {

                                            $query->the_post();

                                            // add this ID to the reimport array for this type
                                            if (sizeOf($potential_reimport_array) < 100) {
                                                $potential_reimport_array[] = get_the_ID();
                                            }

                                            ?>

                                            [<a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>"><?php the_ID(); ?></a>]

                                            <?php
                                        }

                                        wp_reset_postdata();
                                        echo '</td></tr>';
                                    } else {
                                        $term_removal_array[] = $qtype['id'];
                                        echo "<td><a href='/wp/wp-admin/tools.php?page=wp_otis_debug&del_term=".$qtype['id']."'>delete</a></td>";
                                        echo "</tr>";
                                    }

                                endforeach;
                                ?>

                            </table>
                        </div>

                        <p class="poi-count">Associated POIs: <?php echo $questionable_term_poi_counts[$term_slug]; ?></p>
                        <?php if (!empty($potential_reimport_array)): ?>
                            <form method='POST'>
                                <?php

                                echo "<input type='checkbox' checked='checked' name='post_reimport[taxonomy]' value='{$term_slug}'>";

                                for ($i = 0; $i < sizeOf($potential_reimport_array); $i++) {
                                    echo "<input type='checkbox' checked='checked' name='post_reimport[postids][{$i}]' value='{$potential_reimport_array[$i]}'>";
                                }
                                ?>
                                <select name='post_reimport[poi_count]'>
                                    <option value="1" selected="selected">1</option>
                                    <option value="10">10</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="1000">1000</option>
                                </select>
                                <button type='submit'>Re-import POIs</button>
                            </form><br>
                        <?php endif; ?>

                        <?php if (!empty($term_removal_array)): ?>
                            <form method='POST'>
                                <?php for ($i = 0; $i < sizeOf($term_removal_array); $i++) {
                                    echo "<input type='checkbox' checked='checked' name='term_removal[termids][{$i}]' value='{$term_removal_array[$i]}'>";
                                } ?>

                                <button type='submit'>Delete unused terms</button>
                            </form><br>
                        <?php endif; ?>

                        <br>
                        <?php

                    } // if ($term)

                endforeach; // foreach($questionable_terms

            else:
                echo "<button class='refresh'>Refresh debugger</button>";
            endif; // !empty $_POST
            ?>
        </div>
        <?php
    }

    public function get_questionable_term($term = 'type')
    {

        $types = get_categories(array('taxonomy' => $term,'hide_empty' => false));

        $questionable_types = array();

        foreach ( $types as $type ) {
            if (strpos($type->name, '| ') !== false)
                $questionable_types[] = array(
                    'id'=>$type->term_id,
                    'name'=>$type->name,
                    'count'=>$type->count,
                    'term'=>$term,
                    'slug'=>$type->slug
                );
        }

        return $questionable_types;

    }

    public $admin_styles = '
			<style scoped>

			input[type=checkbox]:checked:before,
			input[type=checkbox]::before,
			input[type=checkbox] {
				visibility: hidden;
				position: absolute;
			}
			th {font-weight: bold;}

			.otis-debugger-wrapper .wrap {
				transition:max-height 0.3s ease-out;
				max-height: 0;
				overflow: hidden;
			}
			.otis-debugger-wrapper .active + .wrap  {
				max-height: 1000vh;
				overflow: auto;
			}
			.poi-count {margin-top: 0;}
			.hideshow-pois { display: none; }
			.hideshow-pois::active { outline: none; }
			.hideshow-pois.show { display: inline-block; }
			
			.loading {color: #319b59;}
			
			.hidden {display: none;}

		</style>
	';

    public static function init() {
        static $instance = null;
        if ( ! $instance ) {
            $instance = new WpOtisDebug;
        }
        return $instance;
    }
}

// Get this show on the road
WpOtisDebug::init();
