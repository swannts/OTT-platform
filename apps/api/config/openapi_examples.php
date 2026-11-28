<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Curated OpenAPI Request & Response Examples
    |--------------------------------------------------------------------------
    |
    | Keyed by "{HTTP_METHOD} {path}" (matching Scramble's stripped path,
    | e.g. "POST login" or "GET dashboard-detail-v3"). Any operation not listed
    | here automatically receives schema-derived request and response examples
    | via App\Support\OpenApi\OpenApiExamplesTransformer.
    |
    */
    'operations' => [
        'POST login' => [
            'summary' => 'Customer login',
            'request' => [
                'email' => 'viewer@example.com',
                'password' => '<your-password>',
                'device_id' => 'web-chrome-8f42a1c9',
                'device_name' => 'Chrome on Linux',
                'platform' => 'web',
            ],
            'responses' => [
                200 => [
                    'status' => true,
                    'message' => 'User login successfully.',
                    'data' => [
                        'id' => 101,
                        'first_name' => 'Amina',
                        'last_name' => 'Hassan',
                        'email' => 'viewer@example.com',
                        'mobile' => '+15550192834',
                        'user_type' => 'user',
                        'is_subscribe' => 1,
                        'api_token' => '1|<redacted>',
                        'profile_image' => 'https://cdn.example.com/avatars/user-101.jpg',
                    ],
                ],
            ],
        ],

        'POST register' => [
            'summary' => 'Register customer account',
            'request' => [
                'first_name' => 'Amina',
                'last_name' => 'Hassan',
                'email' => 'amina.hassan@example.com',
                'password' => '<your-password>',
                'confirm_password' => '<your-password>',
                'mobile' => '+15550192834',
                'device_id' => 'ios-iphone-15-pro',
                'device_name' => 'iPhone 15 Pro',
                'platform' => 'ios',
            ],
            'responses' => [
                200 => [
                    'status' => true,
                    'message' => 'Registration successfully.',
                    'data' => [
                        'id' => 102,
                        'first_name' => 'Amina',
                        'last_name' => 'Hassan',
                        'email' => 'amina.hassan@example.com',
                        'api_token' => '2|<redacted>',
                    ],
                ],
            ],
        ],

        'POST social-login' => [
            'summary' => 'Social OAuth login',
            'request' => [
                'email' => 'amina.hassan@example.com',
                'first_name' => 'Amina',
                'last_name' => 'Hassan',
                'login_type' => 'google',
                'device_id' => 'android-pixel-8',
                'device_name' => 'Pixel 8',
                'platform' => 'android',
            ],
            'responses' => [
                200 => [
                    'status' => true,
                    'message' => 'User login successfully.',
                    'data' => [
                        'id' => 102,
                        'email' => 'amina.hassan@example.com',
                        'login_type' => 'google',
                        'api_token' => '3|<redacted>',
                    ],
                ],
            ],
        ],

        'GET dashboard-detail-v3' => [
            'summary' => 'Fetch V3 home dashboard sections',
            'responses' => [
                200 => [
                    'status' => true,
                    'message' => 'Dashboard data retrieved successfully.',
                    'data' => [
                        'slider' => [
                            [
                                'id' => 12,
                                'title' => 'Crescent Horizon',
                                'type' => 'movie',
                                'banner_url' => 'https://cdn.example.com/banners/crescent-horizon.jpg',
                            ],
                        ],
                        'continue_watch' => [],
                        'top_10' => [
                            'name' => 'Top 10 Today',
                            'data' => [
                                [
                                    'id' => 45,
                                    'name' => 'The Lantern Keeper',
                                    'type' => 'movie',
                                    'access' => 'paid',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'GET v3/playback-details' => [
            'summary' => 'Authorize and retrieve playback stream details',
            'responses' => [
                200 => [
                    'status' => true,
                    'message' => 'Playback details retrieved successfully.',
                    'data' => [
                        'session_id' => '0195a6c2-7f3e-7000-8000-123456789abc',
                        'content_id' => 45,
                        'content_type' => 'movie',
                        'stream_url' => 'https://cdn.example.com/streams/45/master.m3u8',
                        'video_upload_type' => 'HLS',
                        'resume_position_seconds' => 420,
                    ],
                ],
            ],
        ],

        'POST save-watchlist' => [
            'summary' => 'Add content to user watchlist',
            'request' => [
                'entertainment_id' => 45,
                'profile_id' => 12,
                'type' => 'movie',
            ],
            'responses' => [
                200 => [
                    'status' => true,
                    'message' => 'Added to watchlist successfully.',
                ],
            ],
        ],

        'POST v3/payments/checkout' => [
            'summary' => 'Initiate subscription or pay-per-view checkout',
            'request' => [
                'type' => 'subscription',
                'plan_id' => 2,
                'payment_method' => 'stripe',
                'coupon_code' => 'WELCOME10',
            ],
            'responses' => [
                200 => [
                    'status' => true,
                    'message' => 'Checkout session created.',
                    'data' => [
                        'intent_id' => 'pi_3Qx9Ab2eZvKYlo2C01234567',
                        'checkout_url' => 'https://checkout.stripe.com/c/pay/cs_test_a1b2c3',
                        'amount' => 9.99,
                        'currency' => 'USD',
                    ],
                ],
            ],
        ],
    ],

    'error_responses' => [
        401 => [
            'description' => 'Unauthenticated',
            'example' => [
                'status' => false,
                'message' => 'Unauthenticated.',
            ],
        ],
        404 => [
            'description' => 'Resource not found',
            'example' => [
                'status' => false,
                'message' => 'Resource not found.',
            ],
        ],
        422 => [
            'description' => 'Validation error',
            'example' => [
                'status' => false,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'field' => ['The field is required.'],
                ],
            ],
        ],
    ],
];
