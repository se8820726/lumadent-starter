<?php

return [
    'routine-examinations' => [
        'slug' => 'routine-examinations',
        'name' => 'Routine examinations',
        'summary' => 'A calm, thorough check-up with time to discuss your questions.',
        'description' => 'Routine examinations help a dentist understand your current oral health and discuss appropriate next steps with you.',
        'featured' => true,
        'icon' => 'calendar-check',
    ],
    'dental-hygiene' => [
        'slug' => 'dental-hygiene',
        'name' => 'Dental hygiene',
        'summary' => 'Professional cleaning and practical guidance for everyday care.',
        'description' => 'A hygiene appointment focuses on professional cleaning and personalised advice for maintaining healthy teeth and gums.',
        'featured' => true,
        'icon' => 'sparkles',
    ],
    'emergency-appointments' => [
        'slug' => 'emergency-appointments',
        'name' => 'Emergency appointments',
        'summary' => 'Prompt assessment when you have an urgent dental concern.',
        'description' => 'An emergency appointment provides an initial assessment, support and an explanation of suitable treatment options.',
        'featured' => true,
        'icon' => 'shield',
    ],
    'teeth-whitening' => [
        'slug' => 'teeth-whitening',
        'name' => 'Teeth whitening',
        'summary' => 'Dentist-supervised options tailored to your smile and preferences.',
        'description' => 'A consultation assesses suitability and explains professionally supervised whitening options, expected care and limitations.',
        'featured' => false,
        'icon' => 'sun',
    ],
    'clear-aligners' => [
        'slug' => 'clear-aligners',
        'name' => 'Clear aligner consultation',
        'summary' => 'Explore discreet orthodontic options with a tailored assessment.',
        'description' => 'A consultation considers your goals and suitability before a dentist explains potential orthodontic approaches.',
        'featured' => true,
        'icon' => 'smile',
    ],
    'dental-implants' => [
        'slug' => 'dental-implants',
        'name' => 'Dental implants',
        'summary' => 'A considered consultation for replacing missing teeth.',
        'description' => 'An implant consultation reviews your circumstances and explains possible stages, alternatives, aftercare and risks.',
        'suitability' => 'Dental implants may be one option for replacing a missing tooth or supporting a restoration. A dentist needs to assess your oral health, medical history and available bone before discussing whether this approach could be appropriate for you.',
        'journey' => [
            [
                'title' => 'Assessment and planning',
                'text' => 'The dentist discusses your goals, examines your mouth and explains any imaging or preparatory care that may be needed before a decision is made.',
            ],
            [
                'title' => 'Placement, when appropriate',
                'text' => 'If you decide to proceed and the treatment is suitable, the implant is placed according to an individual clinical plan and allowed time to heal.',
            ],
            [
                'title' => 'Restoration and review',
                'text' => 'After the implant has been reviewed, a suitable restoration may be fitted and ongoing care discussed with the dental team.',
            ],
        ],
        'alternatives' => [
            'A dental bridge supported by neighbouring teeth.',
            'A removable partial or complete denture.',
            'Leaving the space and monitoring it, when clinically appropriate.',
        ],
        'aftercare' => [
            'Follow the personalised cleaning advice provided by the dental team.',
            'Attend recommended reviews and routine dental examinations.',
            'Contact the clinic if you notice persistent discomfort, swelling or a change around the implant.',
        ],
        'risks' => [
            'Implant treatment involves surgery and may include discomfort, swelling, infection or delayed healing.',
            'An implant may not integrate with the bone or may develop complications over time.',
            'Suitability, healing and long-term maintenance vary from person to person.',
        ],
        'faqs' => [
            [
                'question' => 'How do I know whether implants are suitable for me?',
                'answer' => 'A qualified dentist needs to review your mouth, general health and treatment goals. The consultation may include imaging before any recommendation is made.',
            ],
            [
                'question' => 'How long can implant treatment take?',
                'answer' => 'Timing depends on the clinical plan, any preparatory care and how healing progresses. Your dentist should explain the expected stages for your circumstances.',
            ],
            [
                'question' => 'What if I do not want an implant?',
                'answer' => 'Depending on your circumstances, alternatives may include a bridge, a denture or monitoring the space. A dentist can explain the benefits and limitations of each option.',
            ],
        ],
        'featured' => false,
        'icon' => 'tooth',
    ],
    'cosmetic-dentistry' => [
        'slug' => 'cosmetic-dentistry',
        'name' => 'Cosmetic dentistry',
        'summary' => 'A collaborative conversation about your smile goals.',
        'description' => 'A cosmetic consultation explores your priorities and explains suitable options without promising a particular outcome.',
        'featured' => false,
        'icon' => 'stars',
    ],
];
