<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Message;

/**
 * Suhbatning voronka bosqichini hisoblaydi (front bilan kelishilgan):
 *   yangi | qiziqish | narx | kelishuv | sotildi | sovugan
 *
 * Soddalashtirilgan derivation: conversation.status + xabar mazmuni/yoshi.
 * Kelajakda alohida AI tahlilchi `conversations.stage` ni to'g'ridan-to'g'ri
 * yozadigan bo'lsa, shu bilan almashtiriladi.
 */
class ConversationStage
{
    /**
     * @param  iterable<Message>|null  $messages  oxirgi xabarlar (id desc), bo'lmasa yuklaydi
     */
    public static function for(Conversation $conversation, ?iterable $messages = null): string
    {
        if ($conversation->status === 'closed') {
            // Closed: oxirgi customer xabarida xarid signaliga qaraymiz.
            $messages ??= $conversation->messages()->reorder('id', 'desc')->limit(20)->get();
            $bought = collect($messages)->contains(fn (Message $m) => self::looksLikeOrder($m->content));

            return $bought ? 'sotildi' : 'sovugan';
        }

        $messages ??= $conversation->messages()->reorder('id', 'desc')->limit(20)->get();
        $items = collect($messages);

        // Xabar yo'q yoki faqat bitta kiruvchi — yangi murojaat.
        if ($items->count() <= 1) {
            return 'yangi';
        }

        $text = $items->pluck('content')->filter()->implode(' ');
        $text = mb_strtolower($text);

        if (self::matches($text, ['manzil', 'yetkazib', 'kel', 'oldim', 'olyapman', 'olaman', 'rasmiylash', 'buyurtma berdim', 'jonating'])) {
            return 'kelishuv';
        }

        if (self::matches($text, ['narx', 'qancha', 'qiymat', 'pul', 'chegirma', "so'm", 'som', 'narxi'])) {
            return 'narx';
        }

        // 2+ xabar bor — mijoz qiziqish bildirgan.
        return 'qiziqish';
    }

    private static function looksLikeOrder(?string $text): bool
    {
        if (! $text) {
            return false;
        }
        $t = mb_strtolower($text);

        return self::matches($t, ['rahmat', 'oldim', 'qabul qildim', 'yetkazildi', 'sotildi', 'buyurtma yopildi']);
    }

    /**
     * @param  array<string>  $needles
     */
    private static function matches(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($haystack, $n)) {
                return true;
            }
        }

        return false;
    }
}
