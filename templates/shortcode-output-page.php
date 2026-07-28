<div id="jmli-shortcode" class="wrap">
    <div>
        <div class="jmli-posts">
            <?php
            $logo = is_array($data) ? ($data['meta']['logo'] ?? null) : null;
            $followers = is_array($data) ? ($data['meta']['followers'] ?? null) : null;
            $posts = is_array($data) ? ($data['posts'] ?? []) : [];
            // print_r($posts);

            if (!empty($posts)) {
                $limitedPosts = array_slice($posts, 0, $set_posts_limit);
            
                foreach ($limitedPosts as $post) {
                    if (!is_array($post)) {
                        continue;
                    }
                    $timestamp_ms = isset($post['publishedAt']) ? (int) $post['publishedAt'] : 0;
                    // Convert milliseconds to seconds
                    $timestamp = $timestamp_ms / 1000;
                    // Get current time
                    $current_time = current_time('timestamp');
                    // Get human-readable difference
                    $diff = human_time_diff($timestamp, $current_time);
                    if (($post['lifecycleState'] ?? '') === "PUBLISHED" && ($post['visibility'] ?? '') === "PUBLIC") {
                        ?>
                        <a class="jmli-post-card" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($post['postUrl'] ?? '#'); ?>">
                            <div class="jmli-post-card-header">
                                <div class="jmli-logo"><img width="48px" src="<?php echo esc_url($logo); ?>"
                                        alt="Logo">
                                </div>
                                <div class="company">
                                    <div class="companyName"><?php echo esc_html(ucfirst((string) $companyName)); ?></div>
                                    <div class="companyFollowers"><?php echo esc_html((string) $followers); ?> followers
                                    </div>
                                    <div class="postDate">
                                        <span>
                                            <?php echo esc_html($diff); ?> •
                                        </span>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" data-supported-dps="16x16"
                                            fill="currentColor" class="mercado-match" width="16" height="16" focusable="false">
                                            <path
                                                d="M8 1a7 7 0 107 7 7 7 0 00-7-7zM3 8a5 5 0 011-3l.55.55A1.5 1.5 0 015 6.62v1.07a.75.75 0 00.22.53l.56.56a.75.75 0 00.53.22H7v.69a.75.75 0 00.22.53l.56.56a.75.75 0 01.22.53V13a5 5 0 01-5-5zm6.24 4.83l2-2.46a.75.75 0 00.09-.8l-.58-1.16A.76.76 0 0010 8H7v-.19a.51.51 0 01.28-.45l.38-.19a.74.74 0 01.68 0L9 7.5l.38-.7a1 1 0 00.12-.48v-.85a.78.78 0 01.21-.53l1.07-1.09a5 5 0 01-1.54 9z">
                                            </path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="jmli-post-card-body">
                                <div class="jmli-post-card-body-text">
                                    <?php
                                    $commentary = (string) ($post['commentary'] ?? '');
                                    echo esc_html(mb_strimwidth($commentary, 0, 150, ''));
                                    if (mb_strwidth($commentary) > 150) { echo '&nbsp;&nbsp;&nbsp;<span style="color:#9b9b9b">...more</span>'; }
                                    ?>
                                </div>
                            </div>
                            <div class="jmli-post-card-footer">
                                <?php if (isset($post['content']['media']['imageUrl'])) { ?>
                                    <div class="media">
                                        <img src="<?php echo esc_url($post['content']['media']['imageUrl']); ?>" alt="image">
                                    </div>
                                <?php } ?>
                                <?php if (isset($post['content']['article'])) { ?>
                                    <div class="article">
                                        <div class="thumbnail"><img src="" alt=""></div>
                                        <div class="title"><?php echo esc_html((string) ($post['content']['article']['title'] ?? '')); ?></div>
                                    </div>

                                <?php } ?>
                            </div>
                        </a>
                        <?php
                    } else {
                        echo "no data";
                    }
                }
            } else {
                echo "No posts available.";
            } ?>
        </div>
        <!-- <div class="jmli-see-all-posts">
            <a target="_blank" href="https://www.linkedin.com/company/sahara-natural-resources" class="jmli-see-all-posts-button">
                See All Posts
            </a>
        </div> -->
    </div>
</div>
