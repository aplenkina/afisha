FROM golang:1.24-alpine

WORKDIR /app-go

COPY app-go/go.mod app-go/go.sum ./

RUN go mod download

COPY app-go/. .

RUN go build -o main .

EXPOSE 8080

CMD ["./main"]