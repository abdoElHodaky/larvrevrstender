{{/*
Expand the name of the chart.
*/}}
{{- define "reverse-tender.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
We truncate at 63 chars because some Kubernetes name fields are limited to this (by the DNS naming spec).
If release name contains chart name it will be used as a full name.
*/}}
{{- define "reverse-tender.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Create chart name and version as used by the chart label.
*/}}
{{- define "reverse-tender.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels
*/}}
{{- define "reverse-tender.labels" -}}
helm.sh/chart: {{ include "reverse-tender.chart" . }}
{{ include "reverse-tender.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
app.kubernetes.io/part-of: reverse-tender-platform
{{- end }}

{{/*
Selector labels
*/}}
{{- define "reverse-tender.selectorLabels" -}}
app.kubernetes.io/name: {{ include "reverse-tender.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Create the name of the service account to use
*/}}
{{- define "reverse-tender.serviceAccountName" -}}
{{- if .Values.serviceAccount.create }}
{{- default (include "reverse-tender.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- end }}

{{/*
Blue-Green Environment Labels
*/}}
{{- define "reverse-tender.blueGreenLabels" -}}
{{- if .Values.blueGreen.enabled }}
blue-green.kubernetes.io/enabled: "true"
blue-green.kubernetes.io/active-environment: {{ .Values.blueGreen.activeEnvironment | quote }}
{{- end }}
{{- end }}

{{/*
Generate environment-specific service name
*/}}
{{- define "reverse-tender.environmentServiceName" -}}
{{- $serviceName := index . 0 }}
{{- $environment := index . 1 }}
{{- $context := index . 2 }}
{{- printf "%s-%s-%s" (include "reverse-tender.fullname" $context) $serviceName $environment }}
{{- end }}

{{/*
Generate database connection string
*/}}
{{- define "reverse-tender.databaseUrl" -}}
{{- if .Values.postgresql.enabled }}
{{- printf "postgresql://%s:%s@%s-postgresql:5432/%s" .Values.postgresql.auth.username .Values.postgresql.auth.password (include "reverse-tender.fullname" .) .Values.postgresql.auth.database }}
{{- else }}
{{- .Values.externalDatabase.url }}
{{- end }}
{{- end }}

{{/*
Generate Redis connection string
*/}}
{{- define "reverse-tender.redisUrl" -}}
{{- if .Values.redis.enabled }}
{{- if .Values.redis.auth.enabled }}
{{- printf "redis://:%s@%s-redis-master:6379/0" .Values.redis.auth.password (include "reverse-tender.fullname" .) }}
{{- else }}
{{- printf "redis://%s-redis-master:6379/0" (include "reverse-tender.fullname" .) }}
{{- end }}
{{- else }}
{{- .Values.externalRedis.url }}
{{- end }}
{{- end }}

{{/*
Generate image pull policy
*/}}
{{- define "reverse-tender.imagePullPolicy" -}}
{{- if .Values.image.tag }}
{{- if eq .Values.image.tag "latest" }}
{{- "Always" }}
{{- else }}
{{- .Values.image.pullPolicy | default "IfNotPresent" }}
{{- end }}
{{- else }}
{{- "IfNotPresent" }}
{{- end }}
{{- end }}

{{/*
Generate resource limits and requests
*/}}
{{- define "reverse-tender.resources" -}}
{{- $serviceName := index . 0 }}
{{- $context := index . 1 }}
{{- $serviceConfig := index $context.Values.microservices $serviceName }}
{{- if $serviceConfig.resources }}
{{- toYaml $serviceConfig.resources }}
{{- else }}
{{- toYaml $context.Values.resources }}
{{- end }}
{{- end }}

{{/*
Generate saga-specific environment variables
*/}}
{{- define "reverse-tender.sagaEnvVars" -}}
- name: SAGA_ENABLED
  value: "true"
- name: SAGA_TIMEOUT
  value: "300"
- name: SAGA_RETRY_ATTEMPTS
  value: "3"
- name: SAGA_COMPENSATION_ENABLED
  value: "true"
- name: IDEMPOTENCY_ENABLED
  value: "true"
- name: IDEMPOTENCY_TTL
  value: "3600"
{{- end }}

{{/*
Generate blue-green deployment annotations
*/}}
{{- define "reverse-tender.blueGreenAnnotations" -}}
{{- if .Values.blueGreen.enabled }}
blue-green.kubernetes.io/deployment-strategy: "blue-green"
blue-green.kubernetes.io/traffic-switching: {{ .Values.blueGreen.trafficSwitching.canaryPercentage | quote }}
blue-green.kubernetes.io/stabilization-time: {{ .Values.blueGreen.trafficSwitching.stabilizationTime | quote }}
{{- end }}
{{- end }}
