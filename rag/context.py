# rag/context.py
"""RAG helpers that build a shared knowledge context for both agents."""

from __future__ import annotations

import contextlib
import logging
import os
import re
import sqlite3
from collections import Counter
from dataclasses import dataclass, field
from typing import Iterable, List, Sequence, Tuple

from bridge_agents import Turn


@dataclass
class RetrievedContext:
    """Lightweight container for scored retrieval results."""

    source: str
    text: str
    score: float


@dataclass
class RAGContextManager:
    """Simple retrieval-augmented generation helper.

    The manager collects snippets from recent transcripts and the SQLite
    message log, scores them against the incoming query, and returns
    synthetic turns that are appended to both agents' prompts so they
    receive identical grounding context.
    """

    transcripts_dir: str = "transcripts"
    db_path: str = "bridge.db"
    max_chars: int = 600
    top_k: int = 3
    _corpus: List[Tuple[str, str]] = field(default_factory=list)
    _logger: logging.Logger = field(default_factory=lambda: logging.getLogger("bridge"))

    def __post_init__(self) -> None:
        self._load_corpus()

    @property
    def corpus_size(self) -> int:
        """Expose the current corpus size for diagnostics."""
        return len(self._corpus)

    def _load_corpus(self) -> None:
        """Load corpus entries from transcripts and the SQLite database."""
        self._corpus.clear()
        self._logger.debug("RAG: Loading corpus from transcripts and database")
        self._load_transcripts()
        self._load_database_entries()
        self._logger.debug("RAG: Corpus size after load: %s", len(self._corpus))

    def _load_transcripts(self) -> None:
        if not os.path.isdir(self.transcripts_dir):
            self._logger.debug("RAG: Transcript directory '%s' missing", self.transcripts_dir)
            return

        for root, _, files in os.walk(self.transcripts_dir):
            for name in files:
                if not name.endswith(".md"):
                    continue
                path = os.path.join(root, name)
                try:
                    with open(path, "r", encoding="utf-8") as handle:
                        content = handle.read()
                except OSError as exc:
                    self._logger.debug("RAG: Failed to read transcript %s: %s", path, exc)
                    continue

                for snippet in self._split_snippets(content):
                    self._corpus.append((path, snippet))

    def _load_database_entries(self) -> None:
        if not os.path.exists(self.db_path):
            self._logger.debug("RAG: Database '%s' not found; skipping", self.db_path)
            return

        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.execute(
                "SELECT conversation_id, content FROM messages ORDER BY timestamp DESC LIMIT 250"
            )
            for conv_id, content in cursor.fetchall():
                snippet = str(content or "").strip()
                if snippet:
                    self._corpus.append((f"db:{conv_id}", snippet))
        except sqlite3.Error as exc:
            self._logger.debug("RAG: Failed to read from database: %s", exc)
        finally:
            with contextlib.suppress(Exception):
                conn.close()

    def _split_snippets(self, text: str) -> Iterable[str]:
        """Split large blobs into smaller, useful snippets."""
        cleaned = text.replace("\r\n", "\n")
        for block in cleaned.split("\n\n"):
            snippet = block.strip()
            if snippet:
                yield snippet

    def _tokenize(self, text: str) -> Counter:
        tokens = re.findall(r"\b\w+\b", text.lower())
        return Counter(tokens)

    def _score(self, query_tokens: Counter, doc_tokens: Counter) -> float:
        if not query_tokens or not doc_tokens:
            return 0.0
        shared = sum((query_tokens & doc_tokens).values())
        normaliser = max(sum(query_tokens.values()), 1)
        return shared / normaliser

    def _retrieve(self, query: str) -> List[RetrievedContext]:
        query_tokens = self._tokenize(query)
        scored: List[RetrievedContext] = []
        for source, text in self._corpus:
            score = self._score(query_tokens, self._tokenize(text))
            if score > 0:
                scored.append(RetrievedContext(source=source, text=text, score=score))
        scored.sort(key=lambda item: item.score, reverse=True)
        return scored[: self.top_k]

    def build_shared_turns(self, starter: str, history_texts: Sequence[str]) -> List[Turn]:
        """Return context turns to attach to both agents.

        A concise query is built from the starter and the most recent
        conversation exchanges. Retrieved snippets are truncated to keep
        prompts small and marked as originating from RAG so both agents
        see identical context.
        """

        history_excerpt = " \n".join(history_texts[-6:])
        query = f"Starter: {starter}\nConversation: {history_excerpt}".strip()
        results = self._retrieve(query)
        context_turns: List[Turn] = []
        for idx, result in enumerate(results, start=1):
            trimmed = result.text[: self.max_chars].strip()
            prefix = f"[Shared context {idx} | {result.source}]\n{trimmed}"
            context_turns.append(Turn(author="context", text=prefix))

        if context_turns:
            self._logger.debug("RAG: Prepared %s shared context turns", len(context_turns))
        return context_turns
