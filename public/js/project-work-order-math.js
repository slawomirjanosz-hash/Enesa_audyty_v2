export function lineCents(quantity, price) {
    return Math.round(Math.round(Number(quantity)*100)*Math.round(Number(price)*100)/100);
}
