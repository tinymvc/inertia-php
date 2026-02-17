<?php

namespace Inertia\Props;

/**
 * AlwaysProp
 *
 * Always props are always included in the response, even during partial reloads when they are not explicitly 
 * requested. This is useful for data that should always be fresh, such as 
 * flash messages, error counts, or notification badges.
 *
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 * @link https://inertiajs.com/docs/v2/data-props/partial-reloads
 */
class AlwaysProp extends BaseProp
{
}
