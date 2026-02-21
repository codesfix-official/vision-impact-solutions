/**
 * Orientation JavaScript
 *
 * @package VisionImpactCustomSolutions
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var VICS_Orientation = {

        progressInterval: null,
        pendingAutoplay: false,

        init: function() {
            this.pendingAutoplay = $('#vics-step-video').hasClass('active');
            this.bindEvents();
            this.initVideoPlayer();
            // Prevent body scrolling when overlay is active
            $('body').addClass('vics-overlay-active');

            if (this.pendingAutoplay) {
                this.autoPlayCurrentVideo();
            }
        },

        bindEvents: function() {
            var self = this;

            // Form submission
            $(document).on('submit', '#vics-orientation-form', function(e) {
                e.preventDefault();
                self.submitForm();
            });

            // Video completion
            $(document).on('click', '#vics-access-site', function(e) {
                e.preventDefault();
                
                // Add confirmation to prevent accidental clicks
                // if (!confirm('Are you ready to go to your profile?')) {
                //     return;
                // }
                
                // Remove body scroll prevention
                $('body').removeClass('vics-overlay-active');
                $('#vics-orientation-overlay').fadeOut();
                window.location.href = vicsOrientationData.profileUrl;
            });
        },

        initVideoPlayer: function() {
            var self = this;
            var videoType = vicsOrientationData.videoType;

            // Check if user already has sufficient progress to complete
            if (vicsOrientationData.savedTimestamp > 0 && !vicsOrientationData.videoCompleted) {
                // Simulate a completion check with saved progress
                // This will mark as complete if they have 95%+ progress
                setTimeout(function() {
                    // We can't get duration immediately, so we'll check during playback
                    // But for now, let's assume if they have significant progress, they can complete
                }, 1000);
            }

            if (videoType === 'youtube') {
                self.initYouTubePlayer();
            } else if (videoType === 'vimeo') {
                self.initVimeoPlayer();
            } else if (videoType === 'html5') {
                self.initHTML5Player();
            }
        },

        initYouTubePlayer: function() {
            var self = this;

            if (typeof YT === 'undefined') {
                // Load YouTube API
                var tag = document.createElement('script');
                tag.src = 'https://www.youtube.com/iframe_api';
                var firstScriptTag = document.getElementsByTagName('script')[0];
                firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

                window.onYouTubeIframeAPIReady = function() {
                    self.createYouTubePlayer();
                };
            } else {
                self.createYouTubePlayer();
            }
        },

        createYouTubePlayer: function() {
            var self = this;
            var videoId = vicsOrientationData.videoId;

            if (!videoId) {
                console.error('YouTube video ID not found');
                return;
            }

            var player = new YT.Player('vics-youtube-player', {
                height: '360',
                width: '640',
                videoId: videoId,
                playerVars: {
                    start: Math.floor(vicsOrientationData.savedTimestamp),
                    controls: 0,
                    disablekb: 1,
                    fs: 0,
                    rel: 0,
                    modestbranding: 1,
                    playsinline: 1
                },
                events: {
                    onReady: function(event) {
                        if (vicsOrientationData.savedTimestamp > 0) {
                            $('#vics-resume-notice').show();
                        }
                        // Restrict forward seeking only
                        self.restrictYouTubeSeeking(player);

                        if ($('#vics-step-video').hasClass('active') || self.pendingAutoplay) {
                            self.autoPlayCurrentVideo();
                        }
                        
                        // Check if user already has sufficient progress
                        if (!vicsOrientationData.videoCompleted) {
                            var duration = player.getDuration();
                            var currentTime = vicsOrientationData.savedTimestamp;
                            if (duration > 0) {
                                self.checkCompletion(currentTime, duration);
                            }
                        }
                    },
                    onStateChange: function(event) {
                        if (event.data === YT.PlayerState.PLAYING) {
                            self.trackProgress();
                        }
                        if (event.data === YT.PlayerState.ENDED) {
                            self.markVideoComplete();
                        }
                    }
                }
            });

            window.vicsYouTubePlayer = player;
        },

        restrictYouTubeSeeking: function(player) {
            var self = this;
            var lastTime = 0;
            var maxAllowedTime = 0;
            var playerReady = true;

            // Disable seeking on YouTube player progressbar click
            if (player && player.getPlaylist) {
                try {
                    // Intercept seek events
                    var checkSeeking = setInterval(function() {
                        if (!player.seekBar) {
                            return;
                        }
                        // Try to find and disable seek bar if accessible
                    }, 1000);
                }
                catch (e) {
                    // YouTube API may not expose this
                }
            }

            // Monitor time updates more frequently for accuracy
            setInterval(function() {
                if (player && player.getCurrentTime && playerReady) {
                    var currentTime = player.getCurrentTime();
                    var duration = player.getDuration();

                    // Update max allowed time as video plays normally (only if playing forward)
                    if (currentTime > lastTime) {
                        maxAllowedTime = currentTime;
                    }

                    // Strict: Block any forward seeking beyond current playback
                    if (currentTime > maxAllowedTime + 0.5) { // Very small 0.5s buffer only
                        player.seekTo(maxAllowedTime, true);
                        console.warn('⚠️ Forward seeking disabled. Watch the video sequentially.');
                    }

                    // Block backward seeking more than 5 seconds
                    if (lastTime > 0 && currentTime < lastTime - 5) {
                        player.seekTo(lastTime, true);
                        console.warn('⚠️ Backward seeking disabled.');
                    }

                    lastTime = currentTime;
                }
            }, 300); // Check every 300ms for more responsive blocking
        },
        restrictVimeoSeeking: function(player) {
            var self = this;
            var maxAllowedTime = 0;
            var lastTime = 0;

            // Monitor time updates for Vimeo more frequently
            setInterval(function() {
                player.getCurrentTime().then(function(currentTime) {
                    // Update max allowed time only if playing forward
                    if (currentTime > lastTime) {
                        maxAllowedTime = currentTime;
                    }

                    // Strict: Block any forward seeking beyond current playback
                    if (currentTime > maxAllowedTime + 0.5) { // Very small 0.5s buffer only
                        player.setCurrentTime(maxAllowedTime);
                        console.warn('⚠️ Forward seeking disabled. Watch the video sequentially.');
                    }

                    // Block backward seeking more than 5 seconds
                    if (lastTime > 0 && currentTime < lastTime - 5) {
                        player.setCurrentTime(lastTime);
                        console.warn('⚠️ Backward seeking disabled.');
                    }

                    lastTime = currentTime;
                });
            }, 300); // Check every 300ms for more responsive blocking
        },

        restrictHTML5Seeking: function(player) {
            var self = this;
            var maxAllowedTime = 0;
            var lastTime = 0;

            // Disable seek bar dragging
            if (player && player.controls) {
                $(player).on('seeking', function(e) {
                    if (player.currentTime > maxAllowedTime + 0.5) {
                        player.currentTime = maxAllowedTime;
                        console.warn('⚠️ Forward seeking disabled. Watch the video sequentially.');
                    }
                });
            }

            // Monitor time updates for HTML5 more frequently
            setInterval(function() {
                var currentTime = player.currentTime;

                // Update max allowed time only if playing forward
                if (currentTime > lastTime) {
                    maxAllowedTime = currentTime;
                }

                // Strict: Block any forward seeking beyond current playback
                if (currentTime > maxAllowedTime + 0.5) { // Very small 0.5s buffer only
                    player.currentTime = maxAllowedTime;
                    console.warn('⚠️ Forward seeking disabled. Watch the video sequentially.');
                }

                // Block backward seeking more than 5 seconds
                if (lastTime > 0 && currentTime < lastTime - 5) {
                    player.currentTime = lastTime;
                    console.warn('⚠️ Backward seeking disabled.');
                }

                lastTime = currentTime;
            }, 300); // Check every 300ms for more responsive blocking
        },

        initVimeoPlayer: function() {
            var self = this;
            var videoId = vicsOrientationData.videoId;

            if (!videoId) {
                console.error('Vimeo video ID not found');
                return;
            }

            var player = new Vimeo.Player('vics-vimeo-player', {
                id: videoId,
                controls: false
            });

            player.ready().then(function() {
                if (vicsOrientationData.savedTimestamp > 0) {
                    player.setCurrentTime(vicsOrientationData.savedTimestamp);
                    $('#vics-resume-notice').show();
                }

                if ($('#vics-step-video').hasClass('active') || self.pendingAutoplay) {
                    self.autoPlayCurrentVideo();
                }

                // Restrict forward seeking for Vimeo
                self.restrictVimeoSeeking(player);

                // Check if user already has sufficient progress
                if (!vicsOrientationData.videoCompleted && vicsOrientationData.savedTimestamp > 0) {
                    player.getDuration().then(function(duration) {
                        self.checkCompletion(vicsOrientationData.savedTimestamp, duration);
                    });
                }

                player.on('play', function() {
                    self.trackProgress();
                });

                player.on('ended', function() {
                    self.markVideoComplete();
                });
            }).catch(function(error) {
                console.error('Vimeo player error:', error);
            });

            window.vicsVimeoPlayer = player;
        },

        initHTML5Player: function() {
            var self = this;
            var videoSrc = vicsOrientationData.videoId;

            if (!videoSrc) {
                console.error('HTML5 video source not found');
                return;
            }

            var player = document.getElementById('vics-html5-player');

            if (vicsOrientationData.savedTimestamp > 0) {
                player.currentTime = vicsOrientationData.savedTimestamp;
                $('#vics-resume-notice').show();
            }

            if ($('#vics-step-video').hasClass('active') || self.pendingAutoplay) {
                self.autoPlayCurrentVideo();
            }

            // Restrict forward seeking for HTML5
            self.restrictHTML5Seeking(player);

            // Check if user already has sufficient progress
            if (!vicsOrientationData.videoCompleted && vicsOrientationData.savedTimestamp > 0) {
                self.checkCompletion(vicsOrientationData.savedTimestamp, player.duration);
            }

            $(player).on('play', function() {
                self.trackProgress();
            });

            $(player).on('ended', function() {
                self.markVideoComplete();
            });
        },

        trackProgress: function() {
            var self = this;
            var videoType = vicsOrientationData.videoType;

            // Clear any existing interval
            if (self.progressInterval) {
                clearInterval(self.progressInterval);
            }

            if (videoType === 'youtube' && window.vicsYouTubePlayer) {
                self.progressInterval = setInterval(function() {
                    var currentTime = window.vicsYouTubePlayer.getCurrentTime();
                    var duration = window.vicsYouTubePlayer.getDuration();
                    self.saveProgress(currentTime, duration);
                    self.checkCompletion(currentTime, duration);
                }, 5000);
            } else if (videoType === 'vimeo' && window.vicsVimeoPlayer) {
                self.progressInterval = setInterval(function() {
                    window.vicsVimeoPlayer.getCurrentTime().then(function(currentTime) {
                        window.vicsVimeoPlayer.getDuration().then(function(duration) {
                            self.saveProgress(currentTime, duration);
                            self.checkCompletion(currentTime, duration);
                        });
                    });
                }, 5000);
            } else if (videoType === 'html5') {
                var player = document.getElementById('vics-html5-player');
                self.progressInterval = setInterval(function() {
                    var currentTime = player.currentTime;
                    var duration = player.duration;
                    self.saveProgress(currentTime, duration);
                    self.checkCompletion(currentTime, duration);
                }, 5000);
            }
        },

        saveProgress: function(timestamp, duration) {
            $.ajax({
                url: vicsOrientationData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vics_save_video_progress',
                    nonce: vicsOrientationData.nonce,
                    timestamp: timestamp,
                    duration: duration
                }
            });
        },

        checkCompletion: function(currentTime, duration) {
            if (duration > 0) {
                var progress = (currentTime / duration) * 100;
                // Require 99% completion (essentially the full video) before allowing completion
                // This allows a small buffer for video rounding but forces watching the entire content
                var completionThreshold = 99;
                if (progress >= completionThreshold) {
                    this.markVideoComplete();
                }
            }
        },

        markVideoComplete: function() {
            var self = this;

            $.ajax({
                url: vicsOrientationData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vics_mark_video_complete',
                    nonce: vicsOrientationData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showCompletionStep();
                    }
                }
            });
        },

        submitForm: function() {
            var self = this;
            var formData = new FormData(document.getElementById('vics-orientation-form'));
            formData.append('action', 'vics_submit_orientation_form');
            formData.append('nonce', vicsOrientationData.nonce);

            $.ajax({
                url: vicsOrientationData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.showVideoStep();
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        },

        showVideoStep: function() {
            $('#vics-step-form').removeClass('active');
            $('#vics-step-video').addClass('active');
            this.pendingAutoplay = true;
            this.autoPlayCurrentVideo();
        },

        autoPlayCurrentVideo: function() {
            var self = this;
            var videoType = vicsOrientationData.videoType;

            if (videoType === 'youtube' && window.vicsYouTubePlayer && typeof window.vicsYouTubePlayer.playVideo === 'function') {
                try {
                    window.vicsYouTubePlayer.playVideo();
                    self.pendingAutoplay = false;
                } catch (e) {}
                return;
            }

            if (videoType === 'vimeo' && window.vicsVimeoPlayer && typeof window.vicsVimeoPlayer.play === 'function') {
                window.vicsVimeoPlayer.play().then(function() {
                    self.pendingAutoplay = false;
                }).catch(function() {});
                return;
            }

            if (videoType === 'html5') {
                var player = document.getElementById('vics-html5-player');
                if (player && typeof player.play === 'function') {
                    var playPromise = player.play();
                    if (playPromise && typeof playPromise.then === 'function') {
                        playPromise.then(function() {
                            self.pendingAutoplay = false;
                        }).catch(function() {});
                    }
                }
                return;
            }

            if (self.pendingAutoplay) {
                setTimeout(function() {
                    self.autoPlayCurrentVideo();
                }, 800);
            }
        },

        showCompletionStep: function() {
            var self = this;

            $.ajax({
                url: vicsOrientationData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vics_complete_orientation',
                    nonce: vicsOrientationData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#vics-step-video').removeClass('active');
                        $('#vics-step-complete').addClass('active');
                        $('#vics-welcome-text').text(response.data.message);
                        
                        // Remove auto-redirect - user must click the button
                        // setTimeout(function() {
                        //     $('#vics-orientation-overlay').fadeOut();
                        // }, 3000);
                    } else {
                        alert(response.data);
                    }
                }
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        if (typeof vicsOrientationData !== 'undefined') {
            VICS_Orientation.init();
        }
    });

})(jQuery);
