<?php

namespace App\Enums;

enum AiProcessingType: string
{
    case SpeechRecognition = 'speech_recognition';
    case Ocr = 'ocr';
    case LlmExtraction = 'llm_extraction';
    case ProductMatching = 'product_matching';
}
