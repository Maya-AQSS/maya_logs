{{/*
Nombre base del release. Permite override por fullnameOverride/nameOverride.
*/}}
{{- define "maya-logs.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "maya-logs.fullname" -}}
{{- if .Values.fullnameOverride -}}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" -}}
{{- else -}}
{{- $name := default .Chart.Name .Values.nameOverride -}}
{{- if contains $name .Release.Name -}}
{{- .Release.Name | trunc 63 | trimSuffix "-" -}}
{{- else -}}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" -}}
{{- end -}}
{{- end -}}
{{- end -}}

{{- define "maya-logs.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{/*
Etiquetas comunes a todos los recursos.
*/}}
{{- define "maya-logs.labels" -}}
helm.sh/chart: {{ include "maya-logs.chart" . }}
{{ include "maya-logs.selectorLabels" . }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- with .Values.commonLabels }}
{{ toYaml . }}
{{- end }}
{{- end -}}

{{- define "maya-logs.selectorLabels" -}}
app.kubernetes.io/name: {{ include "maya-logs.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end -}}

{{/*
Etiquetas + selector específicos por componente (backend/frontend/worker/reverb).
*/}}
{{- define "maya-logs.componentLabels" -}}
{{ include "maya-logs.labels" . }}
app.kubernetes.io/component: {{ .component }}
{{- end -}}

{{- define "maya-logs.componentSelectorLabels" -}}
{{ include "maya-logs.selectorLabels" .root }}
app.kubernetes.io/component: {{ .component }}
{{- end -}}

{{/*
Nombre estable por componente. Usado para Service DNS este-oeste:
  maya-logs-backend.maya-logs.svc.cluster.local
*/}}
{{- define "maya-logs.componentName" -}}
{{- printf "maya-logs-%s" .component -}}
{{- end -}}

{{/*
Nombre del Secret a referenciar. Si secret.externalName está definido se
usa ese; si no, el creado por el chart.
*/}}
{{- define "maya-logs.secretName" -}}
{{- if .Values.secret.externalName -}}
{{- .Values.secret.externalName -}}
{{- else -}}
{{- default (printf "%s-secret" (include "maya-logs.fullname" .)) .Values.secret.name -}}
{{- end -}}
{{- end -}}

{{/*
Imagen completa para un componente. Argumentos: dict "root" .Values "componentImage" .Values.backend.image.
*/}}
{{- define "maya-logs.image" -}}
{{- $reg := .root.image.registry -}}
{{- $repo := default .root.image.repository .componentImage.repository -}}
{{- $tag := default .root.image.tag .componentImage.tag -}}
{{- if not $tag -}}
{{- fail "image.tag (o backend.image.tag/etc.) es obligatorio — pasar --set image.tag=<sha-git>" -}}
{{- end -}}
{{- printf "%s/%s:%s" $reg $repo $tag -}}
{{- end -}}

{{/*
Variables env desde ConfigMap + Secret. Usado por todos los pods de backend.
*/}}
{{- define "maya-logs.envFrom" -}}
- configMapRef:
    name: {{ include "maya-logs.fullname" . }}-config
- secretRef:
    name: {{ include "maya-logs.secretName" . }}
{{- end -}}

{{/*
Lifecycle preStop para drenaje suave.
*/}}
{{- define "maya-logs.preStop" -}}
preStop:
  exec:
    command: ["/bin/sh", "-c", "sleep {{ .Values.preStopSleepSeconds | default 5 }}"]
{{- end -}}

{{/*
Volúmenes runtime escribibles (necesarios con readOnlyRootFilesystem).
*/}}
{{- define "maya-logs.runtimeVolumes" -}}
- name: storage-framework
  emptyDir: {}
- name: bootstrap-cache
  emptyDir: {}
- name: storage-logs
  emptyDir: {}
- name: tmp
  emptyDir: {}
{{- end -}}

{{- define "maya-logs.runtimeVolumeMounts" -}}
- name: storage-framework
  mountPath: /var/www/html/storage/framework
- name: bootstrap-cache
  mountPath: /var/www/html/bootstrap/cache
- name: storage-logs
  mountPath: /var/www/html/storage/logs
- name: tmp
  mountPath: /tmp
{{- end -}}
