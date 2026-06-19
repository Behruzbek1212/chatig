<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Conversation\SendMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\ConversationStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Dashboard "Suhbatlar" sahifasi uchun API.
 * Front /chats endpointlariga ulanadi (lib/api/chats.js).
 */
class ConversationController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Conversation::query()->with('customer');

        if ($request->filled('stage')) {
            $stage = (string) $request->string('stage');
            $statusMap = [
                'yangi' => ['open'],
                'qiziqish' => ['ai_handling', 'open'],
                'narx' => ['ai_handling'],
                'kelishuv' => ['ai_handling', 'needs_human'],
                'sotildi' => ['closed'],
                'sovugan' => ['closed'],
            ];
            if (isset($statusMap[$stage])) {
                $query->whereIn('status', $statusMap[$stage]);
            }
        }

        if ($request->filled('q')) {
            $term = (string) $request->string('q');
            $query->whereHas('customer', fn ($sub) => $sub
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('external_id', 'like', "%{$term}%"));
        }

        $conversations = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 30));

        return ConversationResource::collection($conversations);
    }

    public function show(Conversation $chat): JsonResponse
    {
        $chat->load('customer');

        return $this->ok(new ConversationResource($chat));
    }

    public function messages(Conversation $chat): AnonymousResourceCollection
    {
        $messages = $chat->messages()->orderBy('id')->get();

        return MessageResource::collection($messages);
    }

    public function send(SendMessageRequest $request, Conversation $chat): JsonResponse
    {
        $message = Message::create([
            'conversation_id' => $chat->id,
            'role' => 'owner',
            'direction' => 'outbound',
            'content' => $request->validated('text'),
            'status' => 'sent',
            'tokens' => 0,
        ]);

        $chat->update([
            'last_message_at' => $message->created_at,
            'status' => $chat->status === 'needs_human' ? 'needs_human' : $chat->status,
        ]);

        return $this->ok(new MessageResource($message));
    }

    public function markRead(Conversation $chat): JsonResponse
    {
        $chat->update(['last_read_at' => now()]);

        return $this->ok(['ok' => true]);
    }

    /**
     * Sodda AI tahlil: hozir aniq bosqich + xabarlar sonidan kelib chiqqan
     * matnli xulosa. Kelajakda alohida agent (yoki cached Lead.notes) bilan
     * almashtiriladi — front kontrakti shu bo'yicha barqaror.
     */
    public function insight(Conversation $chat): JsonResponse
    {
        $messages = $chat->messages()->orderBy('id')->get();
        $stage = ConversationStage::for($chat, $messages);

        $stageHints = [
            'yangi' => [
                'intent' => 'Yangi murojaat',
                'summary' => 'Mijoz hozirgina yozdi — birinchi javob tezligi muhim.',
                'suggestion' => 'Iliq salomlashing va mahsulot/xizmat haqida aniqlashtiruvchi savol bering.',
            ],
            'qiziqish' => [
                'intent' => 'Qiziqish bildirmoqda',
                'summary' => 'Mijoz mahsulot haqida ma\'lumot so\'rayapti.',
                'suggestion' => 'Aniq variantlarni rasm/narx bilan taklif qiling.',
            ],
            'narx' => [
                'intent' => 'Narx muhokama qilmoqda',
                'summary' => 'Mijoz narx va shartlar bilan qiziqyapti — qaror qabul qilish bosqichi yaqin.',
                'suggestion' => 'Aniq narx, yetkazib berish va imkoniyatlarni qisqa ayting.',
            ],
            'kelishuv' => [
                'intent' => 'Buyurtma rasmiylashtirilmoqda',
                'summary' => 'Mijoz manzil/yetkazib berish haqida gapirmoqda.',
                'suggestion' => 'Manzil va telefon raqamini olib, buyurtmani yopib qo\'ying.',
            ],
            'sotildi' => [
                'intent' => 'Savdo yakunlandi',
                'summary' => 'Mijoz mahsulotni qabul qildi.',
                'suggestion' => 'Fikr-mulohaza so\'rang va keyingi xaridga taklif qiling.',
            ],
            'sovugan' => [
                'intent' => 'Sovib qolgan',
                'summary' => 'Mijoz uzoq vaqt javob bermayapti yoki qiziqishni yo\'qotdi.',
                'suggestion' => 'Yengil eslatma yuboring yoki yangi taklif bilan qayta urinib ko\'ring.',
            ],
        ];

        $hint = $stageHints[$stage] ?? $stageHints['yangi'];

        return $this->ok([
            'stage' => $stage,
            'intent' => $hint['intent'],
            'summary' => $hint['summary'],
            'suggestion' => $hint['suggestion'],
            'products' => [],
        ]);
    }
}
