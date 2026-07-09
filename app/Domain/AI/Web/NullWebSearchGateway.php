<?php

namespace App\Domain\AI\Web;

use App\Contracts\WebSearchGateway;

/**
 * مزوّد بحث معطّل — يُستخدم عند تفعيل Kill Switch. يُرجع [] دائماً.
 */
class NullWebSearchGateway implements WebSearchGateway
{
    public function search(string $query, int $limit = 5): array
    {
        return [];
    }
}
