<h4 style="font-size: 30px;"><?php _e('My Lessons Progress', 'lessons-watch-time'); ?></h4>

<?php if ($extractedData): ?>
    <!-- Tab Navigation -->
    <div class="tab-container skd_front_wList_tab">
        <ul class="tab-navigation">
            <?php foreach ($extractedData as $index => $extract): ?>
                <li>
                    <a href="#courseTab-<?php echo $index; ?>"
                        class="tab-link"
                        data-completed="<?php echo $extract['completion_data']->completed_lessons ?? 0; ?>"
                        data-total="<?php echo $extract['completion_data']->total_lessons ?? 0; ?>" data-inprogress="<?php echo $extract['completion_data']->in_progress_lessons ?? 0; ?>">
                        <?php echo esc_html($extract['menu_name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Tab Content -->
        <?php foreach ($extractedData as $index1 => $extrctData): ?>
            <div id="courseTab-<?php echo $index1; ?>" class="tab-content" style="display: none;">
                <?php
                // Get parent posts and completion data from $data array
                $parent_posts = $extrctData['parent_posts'];
                $completion_data = $extrctData['completion_data'];

                // Extract completion data
                $total_lessons = $completion_data->total_lessons ?? 0;
                $completed_lessons = $completion_data->completed_lessons ?? 0;
                // $incomplete_lessons = $total_lessons - $completed_lessons;
                $in_progress_lessons = $completion_data->in_progress_lessons ?? 0;
                $not_started_lessons = $total_lessons - $completed_lessons - $in_progress_lessons;
                ?>

                <div class="skd-accordion">
                    <div class="accordion">
                        <?php foreach ($parent_posts as $parent): ?>
                            <?php
                            $subposts = $wpdb->get_results($wpdb->prepare(
                                "SELECT 
                                    p.ID AS post_id,
                                    p.post_title,
                                    p.menu_order,
                                    lwp.watch_time,
                                    lwp.is_complete,
                                    lwp.last_updated,
                                    lwp.duration
                                    FROM {$wpdb->posts} AS p
                                    LEFT JOIN {$wpdb->prefix}lesson_video_progress AS lwp 
                                        ON p.ID = lwp.post_id AND lwp.user_id = %d
                                    WHERE p.post_parent = %d AND p.post_type = %s AND p.post_status = 'publish'
                                    ORDER BY p.menu_order ASC",
                                $user_id,
                                $parent->post_parent,
                                $extrctData['post_type']
                            ));
                            // Initialize counters
                            $totalLesson = count($subposts);
                            $complete_count = 0;
                            $in_progress_count = 0;
                            $not_started_count = 0;

                            // Loop through each subpost
                            foreach ($subposts as $subpost) {
                                if ($subpost->is_complete == 1) {
                                    // Mark as complete
                                    $complete_count++;
                                } elseif ($subpost->watch_time > 0) {
                                    // Mark as in progress
                                    $in_progress_count++;
                                } elseif ($subpost->watch_time == 0) {
                                    // Mark as not started
                                    $not_started_count++;
                                }
                            }
                            ?>

                            <div class="accordion-item">
                                <h3 class="accordion-header"><?php echo esc_html($parent->parent_title) . "<span style='color:green'> (  Total: " . $totalLesson . ", Completed: " . $complete_count . ", In Progress: " . $in_progress_count . ", Not Started: " . $not_started_count . " )</span>"; ?></h3>
                                <div class="accordion-content" style="display: none;">
                                    <?php if ($subposts): ?>
                                        <table class="subpost-table">
                                            <thead>
                                                <tr>
                                                    <th><?php _e('Lesson', 'lessons-watch-time'); ?></th>
                                                    <th><?php _e('Watch Time (seconds)', 'lessons-watch-time'); ?></th>
                                                    <th><?php _e('Completion Status', 'lessons-watch-time'); ?></th>
                                                    <th><?php _e('Progress', 'lessons-watch-time'); ?></th>
                                                    <th><?php _e('Last Updated', 'lessons-watch-time'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($subposts as $row): ?>
                                                    <tr>
                                                        <td><?php echo esc_html($row->post_title); ?></td>
                                                        <td><?php echo esc_html($row->watch_time ?? 0); ?></td>
                                                        <td>
                                                            <?php
                                                            if ($row->is_complete) {
                                                                echo '<span class="completed">' . __('Completed', 'lessons-watch-time') . '</span>';
                                                            } else if ($row->watch_time == 0) {
                                                                echo '<span class="incomplete">' . __('Not started', 'lessons-watch-time') . '</span>';
                                                            } else {
                                                                echo '<span class="incomplete">' . __('In Progress', 'lessons-watch-time') . '</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $progress = 0;
                                                            if ($row->is_complete) {
                                                                $progress = 100;
                                                            } else if (!empty($row->duration)) {
                                                                $video_duration = floatval($row->duration);
                                                                if ($video_duration > 0) {
                                                                    $progress = ($row->watch_time / $video_duration) * 100;
                                                                }
                                                            }
                                                            ?>
                                                            <progress value="<?php echo esc_attr($progress); ?>" max="100"></progress>
                                                            <span><?php echo round($progress); ?>%</span>
                                                        </td>
                                                        <td><?php echo esc_html($row->last_updated ?? __('N/A', 'lessons-watch-time')); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <p><?php _e('No lessons found for this parent.', 'lessons-watch-time'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Pie Chart -->
                <div class="completionOuter">
                    <h3 style="font-size: 25px;"><?php _e('Overall Completion', 'lessons-watch-time'); ?></h3>
                    <div class="completion">
                        <div class="completionLessonBox">
                            <p><?php _e('Total Lessons:', 'lessons-watch-time'); ?> <strong><?php echo $total_lessons; ?></strong></p>
                            <p><?php _e('Completed Lessons:', 'lessons-watch-time'); ?> <strong><?php echo $completed_lessons; ?></strong></p>
                            <p><?php _e('In Progress Lessons:', 'lessons-watch-time'); ?> <strong><?php echo $in_progress_lessons; ?></strong></p>
                            <p><?php _e('Not Started Lessons:', 'lessons-watch-time'); ?> <strong><?php echo $not_started_lessons; ?></strong></p>
                        </div>
                        <div style="width: 200px;" class="completionPieChartOuter">
                            <canvas id="completionPieChart-<?php echo $index1; ?>"></canvas>
                        </div>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

    <!-- Tab Navigation Script -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Accordion functionality
            const accordionHeaders = document.querySelectorAll('.accordion-header');
            accordionHeaders.forEach((header) => {
                header.addEventListener('click', () => {
                    const content = header.nextElementSibling;
                    content.style.display = (content.style.display === 'none' || content.style.display === '') ? 'block' : 'none';
                });
            });

            // Tabs functionality
            const tabs = document.querySelectorAll('.tab-link');
            const contents = document.querySelectorAll('.tab-content');

            let chartInstances = {}; // Keep track of chart instances by canvasId

            tabs.forEach((tab, index) => {
                tab.addEventListener('click', (e) => {
                    e.preventDefault();

                    // Hide all content and remove active class from tabs
                    contents.forEach(content => content.style.display = 'none');
                    tabs.forEach(t => t.classList.remove('active'));

                    // Show the targeted content and mark tab as active
                    const target = document.querySelector(tab.getAttribute('href'));
                    target.style.display = 'block';
                    tab.classList.add('active');

                    // Get data attributes for the current tab
                    const completedLessons = parseInt(tab.getAttribute('data-completed'), 10) || 0;
                    const totalLessons = parseInt(tab.getAttribute('data-total'), 10) || 0;
                    const inProgressLessons = parseInt(tab.getAttribute('data-inprogress'), 10) || 0;
                    const notStartedLessons = totalLessons - completedLessons - inProgressLessons;
                    const incompleteLessons = totalLessons - completedLessons;

                    const canvasId = `completionPieChart-${index}`;
                    const canvas = document.getElementById(canvasId);

                    // Destroy any existing chart instance for this canvas
                    if (chartInstances[canvasId]) {
                        chartInstances[canvasId].destroy();
                    }

                    // Initialize a new chart
                    chartInstances[canvasId] = generatePieChart(canvasId, completedLessons, incompleteLessons, inProgressLessons, notStartedLessons);
                });

                // Activate the first tab by default
                if (index === 0) {
                    tab.click();
                }
            });

            // Function to generate pie charts
            function generatePieChart(canvasId, completed, incomplete, inProgress, notStarted) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;

                const ctx = canvas.getContext('2d');

                // Check if there is no data
                if (completed === 0 && incomplete === 0 && inProgress === 0 && notStarted === 0) {
                    // Display a placeholder chart or a message
                    return new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: ['No Data'],
                            datasets: [{
                                data: [1], // Single value as a placeholder
                                backgroundColor: ['rgba(200, 200, 200, 0.6)'],
                                borderColor: ['rgba(200, 200, 200, 1)'],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function() {
                                            return 'No data available';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // Normal chart rendering for valid data
                return new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: ['Completed', 'In Progress', 'Not Started'],
                        datasets: [{
                            data: [completed, inProgress, notStarted],
                            backgroundColor: ['rgba(0, 158, 24, 0.6)', 'rgb(248, 228, 8)', 'rgba(200, 200, 200, 0.6)'],
                            borderColor: ['rgba(0, 158, 24, 0.6)', 'rgb(248, 228, 8)', 'rgba(200, 200, 200, 1)'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(tooltipItem) {
                                        const dataset = tooltipItem.dataset;
                                        const total = dataset.data.reduce((a, b) => a + b, 0);
                                        const value = dataset.data[tooltipItem.dataIndex];
                                        const percentage = ((value / total) * 100).toFixed(2);
                                        return `${tooltipItem.label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }

        });
    </script>

<?php else: ?>
    <p><?php _e('No lessons found.', 'lessons-watch-time'); ?></p>
<?php endif; ?>