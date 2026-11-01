# Auditoría de documentación interna — issue #72

**Español** · [English](documentation-audit.en.md)

[Guía de incorporación](desarrollo.md) · [Arquitectura](arquitectura.md) · [Issue #72](https://github.com/Danieldiazi/joomla-user-tools/issues/72)

## Objetivo y método

Revisión de documentación **interna** de los archivos fuente PHP/JavaScript del proyecto: cabeceras y PHPDoc de clases/métodos, comentarios inline, responsabilidades, explicaciones de invariantes y correspondencia con la arquitectura. Se han consultado los archivos fuente de `main` y se han revisado de forma focalizada los flujos más sensibles. Esta **no** es una auditoría de corrección, seguridad, rendimiento o funcionamiento de las APIs; tampoco considera la falta de PHPDoc en un adaptador trivial como defecto.

**Inventario: 183 archivos fuente** (componentes, vistas y plantillas, módulos, plugins, instalador, scripts y JS; excluye pruebas y manifiestos). Las pruebas se localizan desde la matriz de [arquitectura](arquitectura.md). Estado basado en la revisión de documentación y del rol de cada archivo, no en el número bruto de comentarios.

## Convenciones de clasificación

- **A — Ajustado**: comentario o contrato documental mejorado en esta PR, sin cambio funcional.
- **S — Seguimiento deliberado**: lógica o contrato que merece atención al evolucionar; ya tiene explicaciones sustantivas y no se justifican comentarios genéricos ahora.
- **R — Revisado**: no se identificó una carencia concreta que justifique cambiar el archivo en esta PR.
- **D — Delegación/infraestructura**: controlador fino o proveedor de servicios; el comportamiento ya se deduce de la API y de las clases que delega.
- **V — Vista o presentación**: no necesita comentario línea por línea; la regla y la autorización deben explicarse en la capa correspondiente.

## Hallazgos y resolución

| Prioridad | Archivo o método | Observación y resolución |
|---|---|---|
| Alta | `docs/arquitectura.md` y `docs/architecture.en.md` | La cabecera presentaba la versión 2026.10.5 como vigente; corregida a documentación de `main`. |
| Alta | Arquitectura, persistencia y fallos parciales | Faltaba distinguir los resultados de sesión y los trabajos por tandas de los informes persistidos para «Deshacer bloqueos». Corregido en ambos idiomas. |
| Media | `plugins/task/usertools/src/Extension/Usertools.php::blockInactive()` | Ausencia de contrato local de ejecución y fallos: añadido PHPDoc con identidad del sistema, informe previo, revalidación, checkpoints y resultado. |
| Media | `admin/src/Model/AnonymizeModel.php::step()` | La descripción en una línea no explicaba la revalidación ni el checkpoint por cuenta; ampliada. |
| Media | `admin/src/Service/ReportStore.php` | El comentario describía únicamente informes de tareas aunque el almacén también puede utilizarse para tandas manuales; corregido. |
| Media | `admin/src/Service/AccountGroupAdder.php::add()` | El contrato no explicaba `safeTarget`, estado «cambiado» ni la diferencia entre escritura y fallo al cerrar sesiones; aclarado. |
| Media | `plugins/system/usertools/src/Extension/Usertools.php::onPreprocessMenuItems()` | Aclarado que ocultar enlaces es una comodidad visual y nunca reemplaza la autorización del controlador. |
| Media | `package/script.php` | Comentario de responsabilidad del instalador incompleto tras añadir plugins/módulos; actualizado. |
| Media | `AGENTS.md`, README y nueva guía de incorporación | Incorporada la política «explica el porqué», y trazado del flujo de una operación, riesgos y pruebas; español/inglés. |

### Criterios observados sin cambios innecesarios

- `AccountAnonymizer` ya distingue los datos verificados, los efectos de plugins y las limitaciones de alcance. Es un buen patrón de comentarios sobre el *porqué*.
- `UserDirectory`, `GroupMove` y `GroupRemoval` tienen contratos y reglas de negocio útiles. Métodos largos como `CompareModel::step()` exigen atención en futuros refactors, pero no se debe sustituir esa mejora estructural con comentarios obvios.
- Los proveedores, controladores delgados, getters simples, plantillas y vistas no necesitan PHPDoc indiscriminado. El JavaScript ya contiene comentarios de cascadas y elecciones del operador: no conviene repetir cada asignación.
- **No se detectó una serie de comentarios redundantes que mereciera eliminaciones masivas.** La desactualización confirmada más relevante estaba en las guías de arquitectura, no en una instrucción PHP.
- Esta auditoría no interpreta comentarios ausentes como defectos funcionales. Cuando un flujo cambie, actualizar su documentación en la misma PR es parte de los criterios de `AGENTS.md`.

## Inventario archivo por archivo

| Archivo | Estado |
|---|---|
| **admin/layouts/** | |
| `admin/layouts/usertools/account.php` | V |
| `admin/layouts/usertools/confirm.php` | V |
| `admin/layouts/usertools/exclusions.php` | V |
| `admin/layouts/usertools/steps.php` | V |
| `admin/layouts/usertools/undo.php` | V |
| **admin/** | |
| `admin/services/provider.php` | D |
| **admin/src/Controller/** | |
| `admin/src/Controller/AccessController.php` | D |
| `admin/src/Controller/AccountsController.php` | D |
| `admin/src/Controller/AddgroupsController.php` | D |
| `admin/src/Controller/AgeController.php` | D |
| `admin/src/Controller/AnonymizeController.php` | D |
| `admin/src/Controller/CompareController.php` | D |
| `admin/src/Controller/DeleteController.php` | D |
| `admin/src/Controller/DiagnoseController.php` | D |
| `admin/src/Controller/DisplayController.php` | D |
| `admin/src/Controller/ExportController.php` | D |
| `admin/src/Controller/HandoverController.php` | D |
| `admin/src/Controller/InactiveController.php` | D |
| `admin/src/Controller/MoveController.php` | D |
| `admin/src/Controller/ProfileController.php` | D |
| `admin/src/Controller/ReportsController.php` | D |
| `admin/src/Controller/SyncController.php` | D |
| `admin/src/Controller/ToolControllerTrait.php` | D |
| `admin/src/Controller/UnblockController.php` | D |
| `admin/src/Controller/UngroupController.php` | D |
| `admin/src/Controller/UsageController.php` | D |
| **admin/src/Model/** | |
| `admin/src/Model/AccessModel.php` | R |
| `admin/src/Model/AddgroupsModel.php` | S |
| `admin/src/Model/AgeModel.php` | R |
| `admin/src/Model/AnonymizeModel.php` | A |
| `admin/src/Model/CompareModel.php` | S |
| `admin/src/Model/DeleteModel.php` | S |
| `admin/src/Model/DiagnoseModel.php` | R |
| `admin/src/Model/ExplainModel.php` | R |
| `admin/src/Model/ExportModel.php` | R |
| `admin/src/Model/HandoverModel.php` | R |
| `admin/src/Model/InactiveModel.php` | R |
| `admin/src/Model/MoveModel.php` | R |
| `admin/src/Model/ProfileModel.php` | R |
| `admin/src/Model/ReportModel.php` | R |
| `admin/src/Model/ReportsModel.php` | R |
| `admin/src/Model/SyncModel.php` | R |
| `admin/src/Model/ToolStateTrait.php` | S |
| `admin/src/Model/UnblockModel.php` | R |
| `admin/src/Model/UngroupModel.php` | S |
| `admin/src/Model/UsageModel.php` | R |
| **admin/src/Service/** | |
| `admin/src/Service/AccessExplanation.php` | S |
| `admin/src/Service/AccessExplanationReader.php` | S |
| `admin/src/Service/AccessHealth.php` | R |
| `admin/src/Service/AccessMatrix.php` | R |
| `admin/src/Service/AccessUsage.php` | R |
| `admin/src/Service/AccessUsageReader.php` | R |
| `admin/src/Service/AccountAnonymizer.php` | S |
| `admin/src/Service/AccountBlocker.php` | R |
| `admin/src/Service/AccountDeleter.php` | R |
| `admin/src/Service/AccountExport.php` | R |
| `admin/src/Service/AccountExportReader.php` | R |
| `admin/src/Service/AccountGroupAdder.php` | A |
| `admin/src/Service/AccountGroupSyncer.php` | R |
| `admin/src/Service/AccountIdentity.php` | R |
| `admin/src/Service/AccountMailer.php` | R |
| `admin/src/Service/AccountProfile.php` | R |
| `admin/src/Service/AccountProfileReader.php` | R |
| `admin/src/Service/AccountRegrouper.php` | R |
| `admin/src/Service/AccountUnblocker.php` | R |
| `admin/src/Service/ActionLogger.php` | S |
| `admin/src/Service/AnonymizeSelection.php` | R |
| `admin/src/Service/ArticleReassigner.php` | R |
| `admin/src/Service/BlockBatch.php` | R |
| `admin/src/Service/BulkGroupAssignments.php` | R |
| `admin/src/Service/Comparison.php` | R |
| `admin/src/Service/ContentAge.php` | R |
| `admin/src/Service/ContentAgeReader.php` | R |
| `admin/src/Service/ContentHandover.php` | R |
| `admin/src/Service/ContentHandoverReader.php` | R |
| `admin/src/Service/CsvExport.php` | S |
| `admin/src/Service/DashboardSummary.php` | R |
| `admin/src/Service/Exclusions.php` | R |
| `admin/src/Service/GroupMove.php` | S |
| `admin/src/Service/GroupMover.php` | R |
| `admin/src/Service/GroupRemoval.php` | S |
| `admin/src/Service/GroupSync.php` | R |
| `admin/src/Service/GuidedTours.php` | R |
| `admin/src/Service/InactiveSelection.php` | R |
| `admin/src/Service/InactivityPolicy.php` | R |
| `admin/src/Service/MassBlockGuard.php` | R |
| `admin/src/Service/Navigation.php` | R |
| `admin/src/Service/Operator.php` | R |
| `admin/src/Service/PasswordGenerator.php` | R |
| `admin/src/Service/Registration.php` | R |
| `admin/src/Service/ReportStore.php` | A |
| `admin/src/Service/Settings.php` | R |
| `admin/src/Service/SettingsStore.php` | R |
| `admin/src/Service/Toolkit.php` | R |
| `admin/src/Service/UserDirectory.php` | S |
| `admin/src/Service/UserFacingException.php` | R |
| `admin/src/Service/UserRecord.php` | R |
| **admin/src/View/** | |
| `admin/src/View/Access/HtmlView.php` | V |
| `admin/src/View/Addgroups/HtmlView.php` | V |
| `admin/src/View/Age/HtmlView.php` | V |
| `admin/src/View/Anonymize/HtmlView.php` | V |
| `admin/src/View/Compare/HtmlView.php` | V |
| `admin/src/View/Delete/HtmlView.php` | V |
| `admin/src/View/Diagnose/HtmlView.php` | V |
| `admin/src/View/Explain/HtmlView.php` | V |
| `admin/src/View/Export/HtmlView.php` | V |
| `admin/src/View/Handover/HtmlView.php` | V |
| `admin/src/View/Home/HtmlView.php` | V |
| `admin/src/View/Inactive/HtmlView.php` | V |
| `admin/src/View/Move/HtmlView.php` | V |
| `admin/src/View/Profile/HtmlView.php` | V |
| `admin/src/View/Report/HtmlView.php` | V |
| `admin/src/View/Reports/HtmlView.php` | V |
| `admin/src/View/Schedule/HtmlView.php` | V |
| `admin/src/View/Sync/HtmlView.php` | V |
| `admin/src/View/Unblock/HtmlView.php` | V |
| `admin/src/View/Ungroup/HtmlView.php` | V |
| `admin/src/View/Usage/HtmlView.php` | V |
| `admin/src/View/ViewTrait.php` | V |
| **admin/tmpl/** | |
| `admin/tmpl/access/default.php` | V |
| `admin/tmpl/addgroups/default.php` | V |
| `admin/tmpl/addgroups/progress.php` | V |
| `admin/tmpl/addgroups/result.php` | V |
| `admin/tmpl/addgroups/review.php` | V |
| `admin/tmpl/age/default.php` | V |
| `admin/tmpl/age/list.php` | V |
| `admin/tmpl/anonymize/default.php` | V |
| `admin/tmpl/anonymize/progress.php` | V |
| `admin/tmpl/anonymize/result.php` | V |
| `admin/tmpl/anonymize/review.php` | V |
| `admin/tmpl/compare/default.php` | V |
| `admin/tmpl/compare/progress.php` | V |
| `admin/tmpl/compare/result.php` | V |
| `admin/tmpl/compare/review.php` | V |
| `admin/tmpl/delete/default.php` | V |
| `admin/tmpl/delete/result.php` | V |
| `admin/tmpl/delete/review.php` | V |
| `admin/tmpl/diagnose/default.php` | V |
| `admin/tmpl/explain/default.php` | V |
| `admin/tmpl/export/default.php` | V |
| `admin/tmpl/handover/default.php` | V |
| `admin/tmpl/handover/progress.php` | V |
| `admin/tmpl/handover/result.php` | V |
| `admin/tmpl/handover/review.php` | V |
| `admin/tmpl/handover/select.php` | V |
| `admin/tmpl/home/default.php` | V |
| `admin/tmpl/inactive/default.php` | V |
| `admin/tmpl/inactive/result.php` | V |
| `admin/tmpl/inactive/review.php` | V |
| `admin/tmpl/move/default.php` | V |
| `admin/tmpl/move/result.php` | V |
| `admin/tmpl/move/review.php` | V |
| `admin/tmpl/profile/default.php` | V |
| `admin/tmpl/report/default.php` | V |
| `admin/tmpl/reports/default.php` | V |
| `admin/tmpl/schedule/default.php` | V |
| `admin/tmpl/sync/default.php` | V |
| `admin/tmpl/sync/progress.php` | V |
| `admin/tmpl/sync/result.php` | V |
| `admin/tmpl/sync/review.php` | V |
| `admin/tmpl/unblock/default.php` | V |
| `admin/tmpl/unblock/result.php` | V |
| `admin/tmpl/unblock/review.php` | V |
| `admin/tmpl/ungroup/default.php` | V |
| `admin/tmpl/ungroup/groups.php` | V |
| `admin/tmpl/ungroup/result.php` | V |
| `admin/tmpl/ungroup/review.php` | V |
| `admin/tmpl/usage/default.php` | V |
| **media/** | |
| `media/js/admin.js` | S |
| **modules/** | |
| `modules/mod_usertools/services/provider.php` | D |
| `modules/mod_usertools/src/Dispatcher/Dispatcher.php` | R |
| `modules/mod_usertools/tmpl/default.php` | V |
| `modules/mod_usertools_groups/services/provider.php` | D |
| `modules/mod_usertools_groups/src/Dispatcher/Dispatcher.php` | R |
| `modules/mod_usertools_groups/src/Helper/MembershipHelper.php` | R |
| `modules/mod_usertools_groups/src/Service/GroupTree.php` | R |
| `modules/mod_usertools_groups/tmpl/default.php` | V |
| **package/** | |
| `package/script.php` | A |
| **plugins/** | |
| `plugins/system/usertools/services/provider.php` | D |
| `plugins/system/usertools/src/Extension/Usertools.php` | A |
| `plugins/task/usertools/services/provider.php` | D |
| `plugins/task/usertools/src/Extension/Usertools.php` | A |
| **scripts/** | |
| `scripts/build.php` | R |

La clasificación **S** no significa que la documentación sea incorrecta. Señala puntos cuyo contrato debe revalidarse si cambia la lógica. No se han modificado archivos de prueba ni manifiestos, y los cambios a archivos ejecutables se limitan a comentarios.
