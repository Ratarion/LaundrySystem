from sqlalchemy import BigInteger, DateTime, String, Integer, ForeignKey, Text
from sqlalchemy.orm import Mapped, mapped_column, relationship
from datetime import datetime
from typing import Optional
from app.db.base import Base

class ResidentScoreLog(Base):
    __tablename__ = "resident_score_logs"

    id: Mapped[int] = mapped_column(BigInteger, primary_key=True, autoincrement=True)
    resident_id: Mapped[int] = mapped_column(BigInteger, ForeignKey("residents.id", ondelete="CASCADE"), nullable=False)
    booking_id: Mapped[Optional[int]] = mapped_column(BigInteger, ForeignKey("booking.id", ondelete="SET NULL"), nullable=True)
    delta: Mapped[int] = mapped_column(Integer, nullable=False)
    score_after: Mapped[int] = mapped_column(Integer, nullable=False)
    reason: Mapped[str] = mapped_column(String(50), nullable=False)
    details: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow, nullable=False)

    resident = relationship("Resident")
    booking = relationship("Booking")
