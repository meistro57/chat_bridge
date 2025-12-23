"""
Validation utilities for Chat Bridge.

Handles stop word detection and repetition detection in conversations.
"""

from __future__ import annotations

from difflib import SequenceMatcher
from typing import List


# Configuration constants
STOP_WORDS_DEFAULT = {"goodbye", "end chat", "terminate", "stop", "that is all"}
REPEAT_WINDOW = 6
REPEAT_THRESHOLD = 0.8


def contains_stop_word(text: str, stop_words: set) -> bool:
    """Check if text contains any stop words"""
    lower_text = text.lower()
    return any(word in lower_text for word in stop_words)


def lessen_stop_word_weight(text: str, stop_words: set, weight_factor: float = 0.5) -> bool:
    """Check if text contains stop words with lessened weight/influence"""
    lower_text = text.lower()
    stop_word_matches = [word for word in stop_words if word in lower_text]

    if not stop_word_matches:
        return False

    # Calculate weight based on stop word density and context
    text_length = len(text.split())
    stop_word_density = len(stop_word_matches) / max(text_length, 1)

    # Apply weight factor - higher density or multiple matches increase likelihood
    weighted_threshold = weight_factor * (1 + stop_word_density)

    return stop_word_density >= weighted_threshold


def is_repetitive(texts: List[str], window: int = REPEAT_WINDOW, threshold: float = REPEAT_THRESHOLD) -> bool:
    """Detect if recent messages are too repetitive"""
    if len(texts) < window:
        return False

    recent = texts[-window:]
    similarities = []

    for i in range(len(recent)):
        for j in range(i + 1, len(recent)):
            similarity = SequenceMatcher(None, recent[i], recent[j]).ratio()
            similarities.append(similarity)

    if similarities:
        avg_similarity = sum(similarities) / len(similarities)
        return avg_similarity > threshold

    return False
