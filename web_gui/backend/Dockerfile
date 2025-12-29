# web_gui/backend/Dockerfile
FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

WORKDIR /app

COPY web_gui/backend/requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY web_gui/backend/ ./
# Include default roles for standalone builds; runtime volume can override.
COPY roles.json /app/roles.json

EXPOSE 8000

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]
