<?php

return [
    'title' => 'Post Appreciation',
    'actions' => [
        'appreciate' => 'Appreciate',
        'choose_type' => 'Choose an Appreciation type',
        'submit' => 'Appreciate with :type',
    ],
    'types' => [
        'bermanfaat' => 'Useful',
        'inspiratif' => 'Inspiring',
        'solutif' => 'Solution-oriented',
    ],
    'cost_label' => ':cost :unit',
    'cost_copy' => 'Costs exactly :cost :unit. This amount is spent, not transferred to the post owner.',
    'count' => '{0} No Appreciations|{1} :count Appreciation|[2,*] :count Appreciations',
    'selected' => 'You appreciated this post with :type.',
    'feedback' => [
        'success' => 'Your :type Appreciation was recorded. :cost :unit spent.',
        'already_appreciated' => 'You have already appreciated this post with :type.',
        'insufficient_points' => 'You need at least :cost :unit to appreciate a post.',
        'self_appreciation_forbidden' => 'You cannot appreciate your own post.',
    ],
    'validation' => [
        'type_required' => 'Choose one of the available Appreciation types.',
        'type_invalid' => 'Choose Bermanfaat, Inspiratif, or Solutif.',
    ],
];
