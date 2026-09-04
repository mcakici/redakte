<?php

declare(strict_types=1);

namespace Redakte\Detection;

enum DetectionEvidence: string
{
    case LABEL_MATCH = 'LABEL_MATCH';
    case CHECKSUM_VALID = 'CHECKSUM_VALID';
    case CHECKSUM_INVALID = 'CHECKSUM_INVALID';
    case FORMAT_MATCH = 'FORMAT_MATCH';
    case DICTIONARY_MATCH = 'DICTIONARY_MATCH';
    case ROLE_CONTEXT = 'ROLE_CONTEXT';
    case OCR_NORMALIZED = 'OCR_NORMALIZED';
    case AMBIGUOUS_OVERLAP = 'AMBIGUOUS_OVERLAP';
    case COMPONENT_MATCH = 'COMPONENT_MATCH';
}
