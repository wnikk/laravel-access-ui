<?php

/*
| Refusals of the core, by the code of its exception. The words of the core travel next to these
| under "errors.core", so a translation here never hides what the core said.
*/

return [
    'owner_not_found'      => 'No such owner.',
    'rule_not_found'       => 'No such rule.',
    'duplicate_permission' => 'This owner already holds this permission.',
    'rule_in_use'          => 'The rule is held by owners. Take their permissions away first.',
    'inheritance_loop'     => 'That would make a loop: the other owner already inherits from this one.',
    'invalid_option'       => 'The rule does not accept this option value.',
    'rule_managed_by_code' => 'The rule comes with code. A migration changes it; the panel may reword it and edit its options.',
    'condition'            => 'The condition was refused.',
    'access_rules'         => 'The core refused the change.',
    'unknown'              => 'Something went wrong.',
];
