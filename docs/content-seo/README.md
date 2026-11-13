# سیستم تولید روزانهٔ محتوای Easy Form Builder — فهرست اسناد

> وضعیت: **مرحلهٔ آماده‌سازی تمام شد؛ در انتظار دسترسی‌ها**. هیچ انتشار یا زمان‌بندی‌ای فعال نیست. آخرین به‌روزرسانی: 2026-11-13.
> پیش از هر کاری [00-DECISIONS.md](00-DECISIONS.md) را بخوانید (تصمیم‌ها، یافته‌ها، سؤال‌های باز).

## برای مالک
| سند | چیست |
|---|---|
| [ARTICLE-LIST.fa.md](ARTICLE-LIST.fa.md) | فهرست مقالات: ۶۸ بازنویسی + ۱۳۲ جدید (روز، کد، عنوان، کلیدواژه، پلن، ویدیو) |
| [pipeline/ACCESS-CHECKLIST.md](pipeline/ACCESS-CHECKLIST.md) | همهٔ متغیرهای محیطی و کارهای دسترسی (کاربر ربات، Application Password، mu-plugin، شبکه) |
| [00-DECISIONS.md](00-DECISIONS.md) | تصمیم‌های شما، فرض‌ها، یافته‌های R&D، سؤال‌های باز |
| [EFB-Content-SEO-Plan.fa.md](EFB-Content-SEO-Plan.fa.md) | گزارش مرحلهٔ ۱: ممیزی افزونه، ۸۸ آدرس، فهرست مقالات جدید، تقویم |
| [content-queue.csv](content-queue.csv) | صف اجرایی (وضعیت هر ردیف، شناسهٔ پست‌ها، YouTube) |

## خط لولهٔ فنی ([pipeline/](pipeline/))
`ARCHITECTURE.md` معماری و زمان‌ها • `DAILY-RUNBOOK.md` دستور اجرای روزانه (قالب؛ پس از دسترسی فعال می‌شود) • `PUBLISHING-WORDPRESS.md` انتشار ۳ سایت • `TRANSLATION-WORKFLOW.md` ترجمه/بومی‌سازی • `QA-GATES.md` دروازه‌های کیفیت • `MU-PLUGIN-TEST.md` نتیجهٔ تست mu-plugin • `snippets/efb-content-pipeline.php` خود mu-plugin

## سبک و واژگان ([style/](style/))
`en/`، `ar/`، `de/`، `fa/` — راهنمای سبک، واژه‌نامه، رشته‌های UI رسمی، نمونه‌جفت‌ها، چک‌لیست QA، ابزار lint (ar/de)

## R&D ([rnd/](rnd/))
تحلیل مقالات و تصاویر و ویدیوهای WPForms • `youtube-seo-playbook.md`، `youtube-serp-study.md`، `video-keywords/` • `toolchain-feasibility.md` (آزمایش ساخت ویدیو) • `ai-knowledge-blocks-audit.md`

## قالب‌ها ([templates/](templates/))
`article/` (قالب HTML + JSON-LD + ابزار پر کردن) • `images/` (راهنمای تصویر) • `video/` (اسکریپت، storyboard، motion، بستهٔ YouTube، زیرنویس)

## ابزارها ([tools/](tools/))
`wp-env/` وردپرس محلی • `capture/` ضبط Playwright • `annotate/` حاشیه‌نویسی تصویر • `video/` Remotion • `voice/` صدا و زمان‌بندی • `assemble/` ffmpeg • `pipeline/make_video.sh` همه‌چیز در یک دستور • `srt/validate_srt.py` • `samples/` گزارش‌های نمونه — راهنما: [tools/README.md](tools/README.md)

## قواعد ریپو
ریپوی افزونه **عمومی** است: فقط متن/اسکریپت/CSV اینجا commit می‌شود. ویدیو، تصویر حجیم، پیش‌نویس منتشرنشده و `content-out/` هرگز؛ رمز و کلید فقط در متغیر محیطی.
