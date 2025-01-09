window.addEventListener("load", (event) => {
    (($) => {
        let player = null;

        // Update Time
        timeUpdate();

        function timeUpdate() {
            const iframes = $('iframe.elementor-video-iframe'); // Target the specific iframes
            // console.log('Iframes found:--->>>>>>>>>>>', iframes.length);
            iframes.each(function () {
                // console.log('Iframe found:------------------->>>>>>>', this);

                $(this).on('load', function () {
                    $('.checkbox-loading-spinner').show();
                    // console.log('Iframe loaded:------------------->>>>>>>', this);

                    const src = $(this).attr('src');
                    // console.log('Video iframe src:---------->>>>>', src);

                    const iFrameElement = this;

                    const videoIdMatch = src.match(/video\/(\d+)/); // Extract video ID using regex
                    if (!videoIdMatch) {
                        // console.error('No video ID found in src:------------->>>>>>>', src);
                        return; // Skip if no video ID found
                    }

                    let watchTime = 0;
                    let isComplete = false;
                    let durationVal = 0;

                    const videoId = videoIdMatch[1]; // Extracted video ID
                    // console.log('Extracted Video ID:------------>>>>', videoId);

                    player = new Vimeo.Player(iFrameElement); // Initialize Vimeo Player API
                    // console.log('Vimeo Player initialized:----------->>>>>>', player);

                    // clear previous event listeners
                    player.off('timeupdate');
                    player.off('ended');

                    player.ready().then(function () {
                        const activeLesson = $('.child-lesson.active');
                        const lessonId = activeLesson.data('id');
                        const videoLink = activeLesson.data('video-link');
                        // console.log('Active lesson ID:---------->>>>>', lessonId);
                        // console.log('Active video link:---------->>>>>', videoLink);

                        // Get and log the full duration of the video
                        player.getDuration().then(function (duration) {
                            // console.log(`Full Duration for Video ID ${videoId}: ${duration} seconds`);
                            durationVal = Math.round(duration);
                        }).catch(function (error) {
                            // console.error('Error retrieving video duration:', error);
                        });

                        // console.log('Player ready:------------>>>>>');
                        player.on('timeupdate', function (data) {
                            // console.log('Time update:----------->>>', data);

                            watchTime = Math.round(data.seconds);
                            // console.log('Watch time:------------>>>>>', watchTime);

                            // Send progress to the server every 15 seconds
                            if (watchTime % 10 === 0) {
                                sendProgress(videoId, watchTime, isComplete, lessonId, durationVal);
                            }
                        });

                        player.on('ended', function () {
                            // console.log('Video ended------------>>>>>');
                            isComplete = true;
                            sendProgress(videoId, watchTime, isComplete, lessonId, durationVal);
                        });

                        // Update the checkbox based on the dynamic post_id
                        const checkbox = $('.lesson-complete-checkbox');
                        const checkboxContainer = checkbox.closest('.lwt-checkbox-container');

                        if (checkbox.length) {
                            checkbox.data('post-id', lessonId).prop('disabled', true); // Disable checkbox initially

                            // Add a loading spinner
                            // checkboxContainer.append('<div class="loading-spinner"></div>'); // Add spinner

                            // Fetch the current checkbox status from the server
                            $.ajax({
                                url: VideoTracker.get_check_status_url,
                                method: 'GET',
                                data: { post_id: lessonId },
                                headers: {
                                    'X-WP-Nonce': VideoTracker.nonce,
                                },
                                success: function (response) {
                                    if (response.success) {
                                        checkbox.prop('checked', response.is_complete); // Update the checkbox
                                    }
                                },
                                error: function (xhr, status, error) {
                                    console.error('Error fetching checkbox status:', error);
                                },
                                complete: function () {
                                    // Hide the spinner and enable the checkbox
                                    checkboxContainer.find('.checkbox-loading-spinner').hide();
                                    checkbox.prop('disabled', false);
                                },
                            });
                        }

                    });
                });

                function sendProgress(videoId, watchTime, isComplete, lessonId, duration) {
                    $.ajax({
                        url: VideoTracker.rest_url,
                        method: 'POST',
                        headers: {
                            'X-WP-Nonce': VideoTracker.nonce
                        },
                        data: {
                            video_id: videoId,
                            watch_time: watchTime,
                            is_complete: isComplete,
                            lesson_id: lessonId,
                            duration: duration // Include duration
                        },
                        success: function (response) {
                            // console.log('Progress saved:', response);
                        },
                        error: function (error) {
                            // console.error('Error saving progress:', error);
                        }
                    });
                }

            });
        };

        //on click of #practiseFiles button
        $('#practiseFiles').on('click', function (e) {
            // e.preventDefault();
            const activeLesson = $('.child-lesson.active');
            const lessonId = activeLesson.data('id');
            // console.log('Practise file clicked:', lessonId);

            $.ajax({
                url: VideoTracker.is_download_url,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': VideoTracker.nonce
                },
                data: {
                    lesson_id: lessonId,
                    is_downloaded: true
                },
                success: function (response) {
                    // console.log('Practise file clicked:', response);
                },
                error: function (error) {
                    // console.error('Error saving practise file click:', error);
                }
            });
        });

        $(document).on('change', '.lesson-complete-checkbox', function () {
            const selectedCheckbox = $(this);
            const isCheckedComplete = selectedCheckbox.prop('checked');
            const activeLesson2 = $('.child-lesson.active');
            const lessonId = activeLesson2.data('id');
            console.log(`Checkbox for lesson ${lessonId} changed:`, isCheckedComplete);

            // Update checkbox status on the server
            $.ajax({
                url: VideoTracker.is_checked_url,
                method: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-WP-Nonce': VideoTracker.nonce,
                },
                data: JSON.stringify({
                    post_id: lessonId,
                    is_complete: isCheckedComplete,
                }),
                success: function (response) {
                    if (response.success) {
                        alert('Lesson updated successfully as completed');
                        // console.log(`Checkbox for lesson ${lessonId} updated successfully`);
                    } else {
                        // console.error(`Failed to update checkbox for lesson ${lessonId}`);
                    }
                },
                error: function (xhr, status, error) {
                    // console.error(`Error updating checkbox for lesson ${lessonId}:`, error);
                },
            });
        });
    })(jQuery);
});