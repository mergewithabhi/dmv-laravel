from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import letter
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import inch
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    Image,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "output" / "pdf" / "dmv-warriors-sponsor-pack.pdf"
LOGO = ROOT / "public" / "assets" / "bmv_logo_transparent.png"
RED = colors.HexColor("#D71920")
INK = colors.HexColor("#101114")
MUTED = colors.HexColor("#5B616B")
PAPER = colors.HexColor("#F4F5F7")


class SponsorPackTemplate(BaseDocTemplate):
    def __init__(self, filename: Path):
        super().__init__(
            str(filename),
            pagesize=letter,
            leftMargin=0.65 * inch,
            rightMargin=0.65 * inch,
            topMargin=0.7 * inch,
            bottomMargin=0.65 * inch,
            title="DMV Warriors Sponsorship Pack",
            author="DMV Warriors Basketball",
            subject="Partnership opportunities with the DMV Warriors",
        )
        frame = Frame(
            self.leftMargin,
            self.bottomMargin,
            self.width,
            self.height,
            id="content",
        )
        self.addPageTemplates(
            [PageTemplate(id="main", frames=[frame], onPage=self.decorate_page)]
        )

    def decorate_page(self, canvas, document):
        canvas.saveState()
        canvas.setFillColor(INK)
        canvas.rect(0, letter[1] - 0.18 * inch, letter[0], 0.18 * inch, fill=1, stroke=0)
        canvas.setFillColor(RED)
        canvas.rect(0, letter[1] - 0.25 * inch, letter[0], 0.07 * inch, fill=1, stroke=0)
        canvas.setFont("Helvetica-Bold", 7.5)
        canvas.setFillColor(MUTED)
        canvas.drawString(0.65 * inch, 0.34 * inch, "DMV WARRIORS BASKETBALL")
        canvas.drawRightString(
            letter[0] - 0.65 * inch,
            0.34 * inch,
            f"PARTNERSHIP PACK  |  {document.page}",
        )
        canvas.restoreState()


styles = getSampleStyleSheet()
styles.add(
    ParagraphStyle(
        name="PackTitle",
        parent=styles["Title"],
        fontName="Helvetica-Bold",
        fontSize=34,
        leading=36,
        textColor=INK,
        alignment=TA_CENTER,
        spaceAfter=8,
    )
)
styles.add(
    ParagraphStyle(
        name="PackKicker",
        parent=styles["Normal"],
        fontName="Helvetica-Bold",
        fontSize=10,
        leading=13,
        textColor=RED,
        alignment=TA_CENTER,
        spaceAfter=8,
    )
)
styles.add(
    ParagraphStyle(
        name="SectionTitle",
        parent=styles["Heading1"],
        fontName="Helvetica-Bold",
        fontSize=23,
        leading=26,
        textColor=INK,
        spaceAfter=12,
    )
)
styles.add(
    ParagraphStyle(
        name="CardTitle",
        parent=styles["Heading2"],
        fontName="Helvetica-Bold",
        fontSize=12,
        leading=15,
        textColor=INK,
        spaceAfter=4,
    )
)
styles.add(
    ParagraphStyle(
        name="Body",
        parent=styles["BodyText"],
        fontName="Helvetica",
        fontSize=10,
        leading=15,
        textColor=MUTED,
        spaceAfter=10,
    )
)
styles.add(
    ParagraphStyle(
        name="BodyWhite",
        parent=styles["BodyText"],
        fontName="Helvetica",
        fontSize=10,
        leading=15,
        textColor=colors.white,
    )
)


def section_title(kicker: str, title: str):
    return [
        Paragraph(kicker.upper(), styles["PackKicker"]),
        Paragraph(title, styles["SectionTitle"]),
    ]


def benefit_list(items):
    return Paragraph(
        "<br/>".join(f"<font color='#D71920'>+</font>&nbsp; {item}" for item in items),
        styles["Body"],
    )


def build_pack():
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    doc = SponsorPackTemplate(OUTPUT)
    story = [
        Spacer(1, 0.25 * inch),
        Image(str(LOGO), width=1.8 * inch, height=1.8 * inch),
        Spacer(1, 0.15 * inch),
        Paragraph("PARTNER WITH PURPOSE", styles["PackKicker"]),
        Paragraph("Stronger Together.", styles["PackTitle"]),
        Paragraph(
            "Connect your organization with competitive basketball, athlete development, "
            "and community impact across Washington D.C., Maryland, and Virginia.",
            ParagraphStyle(
                "CoverBody",
                parent=styles["Body"],
                alignment=TA_CENTER,
                fontSize=12,
                leading=18,
                leftIndent=0.55 * inch,
                rightIndent=0.55 * inch,
            ),
        ),
        Spacer(1, 0.25 * inch),
        Table(
            [
                [
                    Paragraph("<b>WASHINGTON D.C.</b>", styles["BodyWhite"]),
                    Paragraph("<b>MARYLAND</b>", styles["BodyWhite"]),
                    Paragraph("<b>VIRGINIA</b>", styles["BodyWhite"]),
                ]
            ],
            colWidths=[2.05 * inch] * 3,
            rowHeights=[0.6 * inch],
            style=TableStyle(
                [
                    ("BACKGROUND", (0, 0), (-1, -1), INK),
                    ("ALIGN", (0, 0), (-1, -1), "CENTER"),
                    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                    ("BOX", (0, 0), (-1, -1), 1, RED),
                    ("INNERGRID", (0, 0), (-1, -1), 1, RED),
                ]
            ),
        ),
        Spacer(1, 0.35 * inch),
        Paragraph(
            "<b>DMV Warriors Basketball</b><br/>info@dmvwarriors.com &nbsp; | &nbsp; "
            "(301) 555-0198 &nbsp; | &nbsp; dmvwarriors.com",
            ParagraphStyle(
                "CoverContact",
                parent=styles["Body"],
                alignment=TA_CENTER,
                fontSize=9,
                leading=14,
            ),
        ),
        PageBreak(),
        *section_title("The opportunity", "A regional platform with purpose"),
        Paragraph(
            "The DMV Warriors bring together athletes, families, fans, and local "
            "organizations through competitive basketball and year-round community "
            "programming. Partners receive meaningful visibility while helping create "
            "opportunities for players and young people across the region.",
            styles["Body"],
        ),
        Spacer(1, 0.08 * inch),
    ]

    stat_cells = [
        ("500K+", "Potential fan reach"),
        ("25+", "Community events"),
        ("18+", "Elite players"),
        ("DC, MD, VA", "Regional presence"),
    ]
    stat_table = Table(
        [
            [
                Paragraph(f"<b><font size='18'>{value}</font></b><br/>{label}", styles["Body"])
                for value, label in stat_cells[:2]
            ],
            [
                Paragraph(f"<b><font size='18'>{value}</font></b><br/>{label}", styles["Body"])
                for value, label in stat_cells[2:]
            ],
        ],
        colWidths=[3.05 * inch, 3.05 * inch],
        rowHeights=[1.05 * inch, 1.05 * inch],
        style=TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), PAPER),
                ("BOX", (0, 0), (-1, -1), 1, colors.HexColor("#D8DADF")),
                ("INNERGRID", (0, 0), (-1, -1), 1, colors.HexColor("#D8DADF")),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("ALIGN", (0, 0), (-1, -1), "CENTER"),
                ("LEFTPADDING", (0, 0), (-1, -1), 16),
                ("RIGHTPADDING", (0, 0), (-1, -1), 16),
            ]
        ),
    )
    story.extend(
        [
            stat_table,
            Spacer(1, 0.25 * inch),
            Paragraph("What partnership can deliver", styles["CardTitle"]),
            benefit_list(
                [
                    "Brand visibility at games, events, and digital touchpoints",
                    "Direct engagement with families and basketball audiences",
                    "Credible alignment with athlete and youth development",
                    "Custom activations shaped around your objectives",
                    "Content, hospitality, and community storytelling opportunities",
                ]
            ),
            PageBreak(),
            *section_title("Partnership levels", "Flexible packages built around your goals"),
        ]
    )

    tiers = [
        (
            "Title Partner",
            [
                "Top-level brand association",
                "Premium uniform and venue visibility",
                "Featured digital and social content",
                "Hospitality and community activations",
            ],
        ),
        (
            "Platinum Partner",
            [
                "High-visibility game-day placement",
                "Website and campaign recognition",
                "Sponsored content opportunities",
                "Select event activation rights",
            ],
        ),
        (
            "Gold Partner",
            [
                "Digital and venue recognition",
                "Game-day announcements",
                "Community event participation",
                "Partner directory placement",
            ],
        ),
        (
            "Community Partner",
            [
                "Local event recognition",
                "Website partner listing",
                "Community program alignment",
                "Accessible custom opportunities",
            ],
        ),
    ]
    tier_rows = []
    for index in range(0, len(tiers), 2):
        row = []
        for name, benefits in tiers[index : index + 2]:
            row.append(
                [
                    Paragraph(name, styles["CardTitle"]),
                    benefit_list(benefits),
                ]
            )
        tier_rows.append(row)
    tier_table = Table(tier_rows, colWidths=[3.05 * inch, 3.05 * inch])
    tier_table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), PAPER),
                ("BOX", (0, 0), (-1, -1), 1, colors.HexColor("#D8DADF")),
                ("INNERGRID", (0, 0), (-1, -1), 1, colors.HexColor("#D8DADF")),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LEFTPADDING", (0, 0), (-1, -1), 16),
                ("RIGHTPADDING", (0, 0), (-1, -1), 16),
                ("TOPPADDING", (0, 0), (-1, -1), 14),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
            ]
        )
    )
    story.extend(
        [
            tier_table,
            Spacer(1, 0.2 * inch),
            Paragraph(
                "All packages are tailored. Inventory, term, category exclusivity, "
                "deliverables, and investment are confirmed in a written proposal.",
                styles["Body"],
            ),
            PageBreak(),
            *section_title("Next steps", "Build a partnership that performs"),
            Paragraph(
                "Our team will translate your audience, community, and business goals "
                "into a focused activation plan with clear deliverables.",
                styles["Body"],
            ),
        ]
    )

    process = [
        ("1", "Discover", "Share your objectives, audience, timing, and priority markets."),
        ("2", "Design", "Select the right mix of game-day, digital, content, and community assets."),
        ("3", "Activate", "Launch with an agreed calendar, owners, approvals, and deliverables."),
        ("4", "Report", "Review delivery and available campaign performance indicators."),
    ]
    process_table = Table(
        [
            [
                Paragraph(f"<b><font color='#D71920'>{number}</font> {title}</b>", styles["CardTitle"]),
                Paragraph(copy, styles["Body"]),
            ]
            for number, title, copy in process
        ],
        colWidths=[1.35 * inch, 4.75 * inch],
        style=TableStyle(
            [
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LINEBELOW", (0, 0), (-1, -2), 0.75, colors.HexColor("#D8DADF")),
                ("TOPPADDING", (0, 0), (-1, -1), 10),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
            ]
        ),
    )
    story.extend(
        [
            process_table,
            Spacer(1, 0.28 * inch),
            Table(
                [
                    [
                        Paragraph(
                            "<b><font size='16'>START THE CONVERSATION</font></b><br/><br/>"
                            "info@dmvwarriors.com<br/>(301) 555-0198<br/>dmvwarriors.com",
                            styles["BodyWhite"],
                        )
                    ]
                ],
                colWidths=[6.1 * inch],
                rowHeights=[1.45 * inch],
                style=TableStyle(
                    [
                        ("BACKGROUND", (0, 0), (-1, -1), INK),
                        ("BOX", (0, 0), (-1, -1), 2, RED),
                        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
                        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                    ]
                ),
            ),
        ]
    )

    doc.build(story)


if __name__ == "__main__":
    build_pack()
    print(OUTPUT)
