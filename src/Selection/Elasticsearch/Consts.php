<?php

namespace G4\DataMapper\Selection\Elasticsearch;

class Consts
{

    const MUST                    = 'must';

    const MUST_NOT                = 'must_not';

    const TERM                    = 'term';

    const TERMS                   = 'terms';

    const WILDCARD                = 'wildcard';

    const WILDCARD_POSITION_BOTH  = 'WILDCARD_POSITION_BOTH';

    const WILDCARD_POSITION_LEFT  = 'WILDCARD_POSITION_LEFT';

    const WILDCARD_POSITION_RIGHT = 'WILDCARD_POSITION_RIGHT';

    const GREATER_THAN            = 'gt';

    const GREATER_THAN_OR_EQUAL   = 'gte';

    const LESS_THAN               = 'lt';

    const LESS_THAN_OR_EQUAL      = 'lte';

    const RANGE                   = 'range';


    static public function rangeParams()
    {
        return [
            static::GREATER_THAN,
            static::GREATER_THAN_OR_EQUAL,
            static::LESS_THAN,
            static::LESS_THAN_OR_EQUAL,
        ];
    }

}